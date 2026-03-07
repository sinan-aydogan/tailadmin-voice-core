"""
System Settings Endpoints.
"""
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List, Optional
from pydantic import BaseModel, Field

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.settings import AppSettings as Setting
from app.config import settings as app_settings
from app.utils.encryption import encrypt_value, decrypt_value, is_encrypted
from loguru import logger


router = APIRouter(prefix="/settings", tags=["settings"])

class SettingCreate(BaseModel):
    key: str
    value: str
    description: str = None

class SettingUpdate(BaseModel):
    value: str
    description: str = None

class SettingResponse(BaseModel):
    id: int
    key: str
    value: str
    description: str = None

    class Config:
        from_attributes = True

class HFTokenUpdate(BaseModel):
    token: str = Field(..., description="HuggingFace API Token (hf_...)")

class HFTokenResponse(BaseModel):
    has_token: bool
    message: str

class SecretKeyUpdate(BaseModel):
    secret_key: str = Field(..., description="API Secret Key for JWT token generation")

class SecretKeyResponse(BaseModel):
    has_key: bool
    message: str
    key_preview: str = None  # First 8 chars of the key for display

@router.get("/", response_model=List[SettingResponse])
def get_all_settings(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Retrieve all configurable system settings."""
    return db.query(Setting).all()

@router.get("/{key}", response_model=SettingResponse)
def get_setting(
    key: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get a specific setting by key."""
    setting = db.query(Setting).filter(Setting.key == key).first()
    if not setting:
        raise HTTPException(status_code=404, detail="Setting not found")
    return setting

@router.post("/", response_model=SettingResponse)
def create_setting(
    setting_in: SettingCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Create a new setting entry."""
    if db.query(Setting).filter(Setting.key == setting_in.key).first():
        raise HTTPException(status_code=400, detail="Setting already exists")
    
    db_setting = Setting(**setting_in.model_dump())
    db.add(db_setting)
    db.commit()
    db.refresh(db_setting)
    return db_setting

@router.put("/{key}", response_model=SettingResponse)
def update_setting(
    key: str,
    setting_in: SettingUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update an existing setting."""
    db_setting = db.query(Setting).filter(Setting.key == key).first()
    if not db_setting:
        raise HTTPException(status_code=404, detail="Setting not found")
        
    db_setting.value = setting_in.value
    if setting_in.description is not None:
        db_setting.description = setting_in.description
        
    db.commit()
    db.refresh(db_setting)
    return db_setting


@router.get("/hf-token/status", response_model=HFTokenResponse)
def get_hf_token_status(
    current_user: User = Depends(get_current_user)
):
    """Check if HuggingFace token is configured."""
    has_token = bool(app_settings.HF_TOKEN)
    return HFTokenResponse(
        has_token=has_token,
        message="HuggingFace token yapılandırılmış" if has_token else "HuggingFace token yapılandırılmamış"
    )


@router.post("/hf-token/update")
def update_hf_token(
    token_data: HFTokenUpdate,
    current_user: User = Depends(get_current_user)
):
    """
    Update HuggingFace API Token.
    Token .env dosyasına kaydedilir ve indirme işlemlerinde kullanılır.
    """
    import os
    from pathlib import Path
    
    # Validate token format
    if not token_data.token.startswith("hf_"):
        raise HTTPException(
            status_code=400, 
            detail="Geçersiz HuggingFace token formatı. Token 'hf_' ile başlamalıdır."
        )
    
    try:
        # Update in-memory settings
        app_settings.HF_TOKEN = token_data.token
        os.environ["HF_TOKEN"] = token_data.token
        
        # Update .env file
        env_path = Path(".env")
        env_content = ""
        
        if env_path.exists():
            with open(env_path, "r", encoding="utf-8") as f:
                env_content = f.read()
        
        # Check if HF_TOKEN already exists in .env
        if "HF_TOKEN=" in env_content:
            # Replace existing token
            lines = env_content.split("\n")
            new_lines = []
            for line in lines:
                if line.startswith("HF_TOKEN="):
                    new_lines.append(f"HF_TOKEN={token_data.token}")
                else:
                    new_lines.append(line)
            env_content = "\n".join(new_lines)
        else:
            # Add new token
            if env_content and not env_content.endswith("\n"):
                env_content += "\n"
            env_content += f"\n# HuggingFace API Token (for authenticated downloads)\nHF_TOKEN={token_data.token}\n"
        
        with open(env_path, "w", encoding="utf-8") as f:
            f.write(env_content)
        
        logger.info(f"HuggingFace token updated by user {current_user.username}")
        
        return {
            "success": True,
            "message": "HuggingFace token başarıyla güncellendi. İndirme işlemlerinde kullanılacak."
        }
        
    except Exception as e:
        logger.error(f"Failed to update HF token: {e}")
        raise HTTPException(status_code=500, detail=f"Token güncellenirken hata: {str(e)}")


@router.delete("/hf-token")
def delete_hf_token(
    current_user: User = Depends(get_current_user)
):
    """Remove HuggingFace API Token."""
    import os
    from pathlib import Path
    
    try:
        # Clear in-memory settings
        app_settings.HF_TOKEN = None
        if "HF_TOKEN" in os.environ:
            del os.environ["HF_TOKEN"]
        
        # Update .env file
        env_path = Path(".env")
        if env_path.exists():
            with open(env_path, "r", encoding="utf-8") as f:
                env_content = f.read()
            
            # Remove HF_TOKEN line
            lines = env_content.split("\n")
            new_lines = [line for line in lines if not line.startswith("HF_TOKEN=")]
            env_content = "\n".join(new_lines)
            
            with open(env_path, "w", encoding="utf-8") as f:
                f.write(env_content)
        
        logger.info(f"HuggingFace token removed by user {current_user.username}")
        
        return {
            "success": True,
            "message": "HuggingFace token kaldırıldı."
        }
        
    except Exception as e:
        logger.error(f"Failed to remove HF token: {e}")
        raise HTTPException(status_code=500, detail=f"Token kaldırılırken hata: {str(e)}")


# Secret Key Management Endpoints

@router.get("/secret-key/status", response_model=SecretKeyResponse)
def get_secret_key_status(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Check if API Secret Key is configured."""
    # Check database first
    db_setting = db.query(Setting).filter(Setting.key == "SECRET_KEY").first()
    
    has_key = False
    key_preview = None
    
    if db_setting and db_setting.value:
        try:
            # Decrypt if encrypted
            if is_encrypted(db_setting.value):
                decrypted = decrypt_value(db_setting.value)
                has_key = True
                key_preview = decrypted[:8] + "..." if len(decrypted) > 8 else decrypted
            else:
                has_key = True
                key_preview = db_setting.value[:8] + "..." if len(db_setting.value) > 8 else db_setting.value
        except Exception:
            has_key = True  # Value exists but can't decrypt
            key_preview = "********"
    elif app_settings.SECRET_KEY and app_settings.SECRET_KEY != "your-secret-key-change-in-production":
        has_key = True
        key_preview = app_settings.SECRET_KEY[:8] + "..." if len(app_settings.SECRET_KEY) > 8 else app_settings.SECRET_KEY
    
    return SecretKeyResponse(
        has_key=has_key,
        message="API Secret Key yapılandırılmış" if has_key else "API Secret Key yapılandırılmamış",
        key_preview=key_preview
    )


@router.post("/secret-key/update")
def update_secret_key(
    key_data: SecretKeyUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Update API Secret Key.
    Key is encrypted before storing in database.
    """
    import os
    from pathlib import Path
    
    # Validate key length
    if len(key_data.secret_key) < 16:
        raise HTTPException(
            status_code=400,
            detail="Secret key en az 16 karakter olmalıdır."
        )
    
    try:
        # Encrypt the secret key before storing
        encrypted_key = encrypt_value(key_data.secret_key)
        
        # Store in database
        db_setting = db.query(Setting).filter(Setting.key == "SECRET_KEY").first()
        if db_setting:
            db_setting.value = encrypted_key
            db_setting.description = "API Secret Key (encrypted)"
        else:
            db_setting = Setting(
                key="SECRET_KEY",
                value=encrypted_key,
                tab="security",
                description="API Secret Key (encrypted)"
            )
            db.add(db_setting)
        
        db.commit()
        
        # Update in-memory settings
        app_settings.SECRET_KEY = key_data.secret_key
        os.environ["SECRET_KEY"] = key_data.secret_key
        
        # Update .env file
        env_path = Path(".env")
        env_content = ""
        
        if env_path.exists():
            with open(env_path, "r", encoding="utf-8") as f:
                env_content = f.read()
        
        # Check if SECRET_KEY already exists in .env
        if "SECRET_KEY=" in env_content:
            lines = env_content.split("\n")
            new_lines = []
            for line in lines:
                if line.startswith("SECRET_KEY="):
                    new_lines.append(f"SECRET_KEY={key_data.secret_key}")
                else:
                    new_lines.append(line)
            env_content = "\n".join(new_lines)
        else:
            if env_content and not env_content.endswith("\n"):
                env_content += "\n"
            env_content += f"\n# API Secret Key for JWT token generation\nSECRET_KEY={key_data.secret_key}\n"
        
        with open(env_path, "w", encoding="utf-8") as f:
            f.write(env_content)
        
        logger.info(f"Secret key updated by user {current_user.username}")
        
        return {
            "success": True,
            "message": "API Secret Key başarıyla güncellendi ve şifreli olarak saklandı.",
            "key_preview": key_data.secret_key[:8] + "..."
        }
        
    except Exception as e:
        logger.error(f"Failed to update secret key: {e}")
        raise HTTPException(status_code=500, detail=f"Secret key güncellenirken hata: {str(e)}")


@router.delete("/secret-key")
def delete_secret_key(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Remove API Secret Key from database (keeps in .env)."""
    try:
        db_setting = db.query(Setting).filter(Setting.key == "SECRET_KEY").first()
        if db_setting:
            db.delete(db_setting)
            db.commit()
        
        logger.info(f"Secret key removed from database by user {current_user.username}")
        
        return {
            "success": True,
            "message": "API Secret Key veritabanından kaldırıldı (env dosyasında saklanmaya devam ediyor)."
        }
        
    except Exception as e:
        logger.error(f"Failed to remove secret key: {e}")
        raise HTTPException(status_code=500, detail=f"Secret key kaldırılırken hata: {str(e)}")
