"""
Authentication routing endpoints.
"""
from datetime import timedelta
from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session

from app.database import get_db
from app.models.user import User
from app.schemas.auth import Token, UserResponse, UserPreferencesUpdate, UserPreferencesResponse
from app.auth.service import verify_password, create_access_token
from app.auth.dependencies import get_current_user
from app.config import settings
from pydantic import BaseModel

router = APIRouter(prefix="/auth", tags=["auth"])

@router.post("/login", response_model=Token)
async def login_for_access_token(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    user = db.query(User).filter(User.username == form_data.username).first()
    if not user or not verify_password(form_data.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect username or password",
            headers={"WWW-Authenticate": "Bearer"},
        )
        
    access_token_expires = timedelta(minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user.username}, expires_delta=access_token_expires
    )
    return {"access_token": access_token, "token_type": "bearer", "expires_in": settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60}

@router.get("/me", response_model=UserResponse)
async def read_users_me(current_user: User = Depends(get_current_user)):
    return current_user

@router.get("/me/preferences", response_model=UserPreferencesResponse)
async def get_user_preferences(current_user: User = Depends(get_current_user)):
    """Get current user's theme and language preferences."""
    return {
        "theme": current_user.theme or "light",
        "language": current_user.language or "tr"
    }

@router.put("/me/preferences", response_model=UserPreferencesResponse)
async def update_user_preferences(
    prefs: UserPreferencesUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update current user's theme and language preferences."""
    if prefs.theme is not None:
        current_user.theme = prefs.theme
    if prefs.language is not None:
        current_user.language = prefs.language
    
    db.commit()
    db.refresh(current_user)
    
    return {
        "theme": current_user.theme,
        "language": current_user.language
    }
