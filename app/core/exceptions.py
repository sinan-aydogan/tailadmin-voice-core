"""
Custom exceptions for the application.
"""
from fastapi import HTTPException, status

class NotFoundException(HTTPException):
    def __init__(self, detail: str = "Resource not found"):
        super().__init__(status_code=status.HTTP_404_NOT_FOUND, detail=detail)

class ModelNotReadyException(HTTPException):
    def __init__(self, detail: str = "Model is not downloaded or ready to use."):
        super().__init__(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=detail)

class InvalidRequestException(HTTPException):
    def __init__(self, detail: str = "Invalid request parameters."):
        super().__init__(status_code=status.HTTP_400_BAD_REQUEST, detail=detail)
