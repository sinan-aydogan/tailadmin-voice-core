import pytest
from fastapi.testclient import TestClient
from main import app
from app.config import settings

def test_read_root():
    with TestClient(app) as client:
        response = client.get("/")
        assert response.status_code == 200
        assert response.json() == {"status": "ok", "service": "TailAdmin Voice Core API"}

def test_login_success():
    with TestClient(app) as client:
        # Because db_init creates the default admin user automatically on app startup,
        # we can just test the login logic here using the default credentials.
        response = client.post(
            "/auth/login",
            data={
                "username": settings.DEFAULT_USERNAME,
                "password": settings.DEFAULT_PASSWORD
            }
        )
        assert response.status_code == 200
        data = response.json()
        assert "access_token" in data
        assert data["token_type"] == "bearer"

def test_login_failure():
    with TestClient(app) as client:
        response = client.post(
            "/auth/login",
            data={
                "username": "wronguser",
                "password": "wrongpassword"
            }
        )
        assert response.status_code == 401
        assert response.json() == {"detail": "Incorrect username or password"}

