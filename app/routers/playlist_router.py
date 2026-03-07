"""
Playlist (Job List) API endpoints.
"""
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from loguru import logger

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.playlist import Playlist, PlaylistItem, PlaylistStatus, PlaylistItemStatus
from app.schemas.playlist import (
    PlaylistCreate, PlaylistUpdate, PlaylistResponse, PlaylistListResponse,
    PlaylistItemCreate, PlaylistItemUpdate, PlaylistItemResponse,
    PlaylistReorderRequest, PlaylistStatusResponse
)
from app.queue.worker import submit_tts_task

router = APIRouter(prefix="/playlists", tags=["playlists"])


@router.get("", response_model=List[PlaylistListResponse])
async def list_playlists(
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Get all playlists for current user."""
    playlists = db.query(Playlist).filter(
        Playlist.user_id == current_user.id
    ).order_by(Playlist.created_at.desc()).all()
    
    # Add computed fields
    result = []
    for playlist in playlists:
        data = {
            **playlist.__dict__,
            'progress_percentage': playlist.get_progress_percentage(),
            'duration_seconds': playlist.get_duration_seconds()
        }
        result.append(data)
    
    return result


@router.post("", response_model=PlaylistResponse, status_code=status.HTTP_201_CREATED)
async def create_playlist(
    playlist_data: PlaylistCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Create a new playlist with items."""
    # Create playlist
    playlist = Playlist(
        name=playlist_data.name,
        description=playlist_data.description,
        use_single_model=playlist_data.use_single_model,
        single_model_id=playlist_data.single_model_id,
        single_profile_id=playlist_data.single_profile_id,
        user_id=current_user.id,
        total_items=len(playlist_data.items)
    )
    
    db.add(playlist)
    db.flush()  # Get playlist ID
    
    # Create items
    for idx, item_data in enumerate(playlist_data.items):
        item = PlaylistItem(
            playlist_id=playlist.id,
            text=item_data.text,
            position=idx,
            model_id=item_data.model_id if not playlist_data.use_single_model else None,
            profile_id=item_data.profile_id if not playlist_data.use_single_model else None
        )
        db.add(item)
    
    db.commit()
    db.refresh(playlist)
    
    logger.info(f"Created playlist '{playlist.name}' with {playlist.total_items} items")
    return {
        **playlist.__dict__,
        'progress_percentage': playlist.get_progress_percentage(),
        'duration_seconds': playlist.get_duration_seconds()
    }


@router.get("/{playlist_id}", response_model=PlaylistResponse)
async def get_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Get a specific playlist with all items."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    # Add computed fields to items
    items = []
    for item in playlist.items:
        items.append({
            **item.__dict__,
            'duration_seconds': item.get_duration_seconds()
        })
    
    return {
        **playlist.__dict__,
        'progress_percentage': playlist.get_progress_percentage(),
        'duration_seconds': playlist.get_duration_seconds(),
        'items': items
    }


@router.put("/{playlist_id}", response_model=PlaylistResponse)
async def update_playlist(
    playlist_id: int,
    playlist_data: PlaylistUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Update playlist details."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    # Update fields
    for field, value in playlist_data.dict(exclude_unset=True).items():
        setattr(playlist, field, value)
    
    db.commit()
    db.refresh(playlist)
    
    return {
        **playlist.__dict__,
        'progress_percentage': playlist.get_progress_percentage(),
        'duration_seconds': playlist.get_duration_seconds(),
        'items': [{**item.__dict__, 'duration_seconds': item.get_duration_seconds()} 
                  for item in playlist.items]
    }


@router.delete("/{playlist_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Delete a playlist and all its items."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    db.delete(playlist)
    db.commit()
    
    logger.info(f"Deleted playlist '{playlist.name}'")
    return None


@router.post("/{playlist_id}/items", response_model=PlaylistItemResponse)
async def add_playlist_item(
    playlist_id: int,
    item_data: PlaylistItemCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Add a new item to playlist."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    # Get next position
    max_position = db.query(PlaylistItem).filter(
        PlaylistItem.playlist_id == playlist_id
    ).count()
    
    item = PlaylistItem(
        playlist_id=playlist_id,
        text=item_data.text,
        position=max_position,
        model_id=item_data.model_id if not playlist.use_single_model else None,
        profile_id=item_data.profile_id if not playlist.use_single_model else None
    )
    
    db.add(item)
    playlist.total_items += 1
    db.commit()
    db.refresh(item)
    
    return {
        **item.__dict__,
        'duration_seconds': item.get_duration_seconds()
    }


@router.put("/{playlist_id}/items/{item_id}", response_model=PlaylistItemResponse)
async def update_playlist_item(
    playlist_id: int,
    item_id: int,
    item_data: PlaylistItemUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Update a playlist item."""
    item = db.query(PlaylistItem).join(Playlist).filter(
        PlaylistItem.id == item_id,
        PlaylistItem.playlist_id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not item:
        raise HTTPException(status_code=404, detail="Item not found")
    
    for field, value in item_data.dict(exclude_unset=True).items():
        setattr(item, field, value)
    
    db.commit()
    db.refresh(item)
    
    return {
        **item.__dict__,
        'duration_seconds': item.get_duration_seconds()
    }


@router.delete("/{playlist_id}/items/{item_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_playlist_item(
    playlist_id: int,
    item_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Delete a playlist item."""
    item = db.query(PlaylistItem).join(Playlist).filter(
        PlaylistItem.id == item_id,
        PlaylistItem.playlist_id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not item:
        raise HTTPException(status_code=404, detail="Item not found")
    
    playlist = item.playlist
    db.delete(item)
    playlist.total_items -= 1
    
    # Reorder remaining items
    remaining_items = db.query(PlaylistItem).filter(
        PlaylistItem.playlist_id == playlist_id,
        PlaylistItem.id != item_id
    ).order_by(PlaylistItem.position).all()
    
    for idx, remaining_item in enumerate(remaining_items):
        remaining_item.position = idx
    
    db.commit()
    return None


@router.post("/{playlist_id}/reorder")
async def reorder_playlist_items(
    playlist_id: int,
    reorder_data: PlaylistReorderRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Reorder playlist items."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    # Update positions based on new order
    for new_position, item_id in enumerate(reorder_data.item_ids):
        item = db.query(PlaylistItem).filter(
            PlaylistItem.id == item_id,
            PlaylistItem.playlist_id == playlist_id
        ).first()
        
        if item:
            item.position = new_position
    
    db.commit()
    return {"message": "Items reordered successfully"}


@router.post("/{playlist_id}/process")
async def process_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Start processing a playlist."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    if playlist.status == PlaylistStatus.PROCESSING:
        raise HTTPException(status_code=400, detail="Playlist is already processing")
    
    if playlist.status == PlaylistStatus.COMPLETED:
        # Reset for reprocessing
        playlist.status = PlaylistStatus.PENDING
        playlist.completed_items = 0
        playlist.failed_items = 0
        for item in playlist.items:
            item.status = PlaylistItemStatus.QUEUED
            item.error_message = None
            item.output_path = None
    
    playlist.status = PlaylistStatus.PROCESSING
    playlist.started_at = datetime.utcnow()
    db.commit()
    
    logger.info(f"Started processing playlist '{playlist.name}' with {len(playlist.items)} items")
    
    # Submit each item to the queue
    processed_count = 0
    for item in playlist.items:
        if item.status == PlaylistItemStatus.QUEUED:
            try:
                # Determine model and profile to use
                model_id = item.model_id or playlist.single_model_id
                profile_id = item.profile_id or playlist.single_profile_id
                
                if not model_id:
                    logger.warning(f"Skipping item {item.id}: no model specified")
                    item.status = PlaylistItemStatus.FAILED
                    item.error_message = "No TTS model specified"
                    continue
                
                # Submit to queue
                task = submit_tts_task(
                    text=item.text,
                    model_id=model_id,
                    profile_id=profile_id,
                    user_id=current_user.id,
                    playlist_item_id=item.id
                )
                
                item.status = PlaylistItemStatus.PROCESSING
                processed_count += 1
                logger.info(f"Submitted playlist item {item.id} to queue as task {task.id}")
                
            except Exception as e:
                logger.error(f"Failed to submit playlist item {item.id}: {e}")
                item.status = PlaylistItemStatus.FAILED
                item.error_message = str(e)
    
    db.commit()
    
    return {
        "message": f"Playlist processing started ({processed_count} items queued)",
        "playlist_id": playlist_id,
        "queued_items": processed_count
    }


@router.post("/{playlist_id}/pause")
async def pause_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Pause playlist processing."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    if playlist.status != PlaylistStatus.PROCESSING:
        raise HTTPException(status_code=400, detail="Playlist is not processing")
    
    playlist.status = PlaylistStatus.PAUSED
    db.commit()
    
    logger.info(f"Paused playlist '{playlist.name}'")
    return {"message": "Playlist paused", "playlist_id": playlist_id}


@router.post("/{playlist_id}/resume")
async def resume_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Resume paused playlist."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    if playlist.status != PlaylistStatus.PAUSED:
        raise HTTPException(status_code=400, detail="Playlist is not paused")
    
    playlist.status = PlaylistStatus.PROCESSING
    db.commit()
    
    logger.info(f"Resumed playlist '{playlist.name}'")
    return {"message": "Playlist resumed", "playlist_id": playlist_id}


@router.post("/{playlist_id}/stop")
async def stop_playlist(
    playlist_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Stop playlist processing."""
    playlist = db.query(Playlist).filter(
        Playlist.id == playlist_id,
        Playlist.user_id == current_user.id
    ).first()
    
    if not playlist:
        raise HTTPException(status_code=404, detail="Playlist not found")
    
    if playlist.status not in [PlaylistStatus.PROCESSING, PlaylistStatus.PAUSED]:
        raise HTTPException(status_code=400, detail="Playlist is not active")
    
    playlist.status = PlaylistStatus.PENDING
    db.commit()
    
    logger.info(f"Stopped playlist '{playlist.name}'")
    return {"message": "Playlist stopped", "playlist_id": playlist_id}


from datetime import datetime
