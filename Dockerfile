# Voice Core - Docker Image with CUDA GPU Support
# Base image: PyTorch with CUDA 12.1 support
FROM pytorch/pytorch:2.1.0-cuda12.1-cudnn8-runtime

# Set working directory
WORKDIR /app

# Install system dependencies
# - ffmpeg: Required for audio processing
# - libsndfile1: Required for audio file I/O
# - git: Required for some pip dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    libsndfile1 \
    git \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements first for better layer caching
COPY requirements.txt .

# Install Python dependencies
# Note: PyTorch is already installed in the base image
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Create necessary directories
RUN mkdir -p data/models data/outputs data/profiles data/uploads logs

# Expose ports
# 5001: API Server
# 5002: Frontend Server (optional, can be served separately)
EXPOSE 5001 5002

# Environment variables
ENV PYTHONUNBUFFERED=1
ENV PYTHONDONTWRITEBYTECODE=1
ENV HF_HOME=/app/data/models/.cache/huggingface
ENV TORCH_HOME=/app/data/models/.cache/torch

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD python -c "import requests; requests.get('http://localhost:5001/health')" || exit 1

# Default command: Start the API server
CMD ["python", "main.py"]
