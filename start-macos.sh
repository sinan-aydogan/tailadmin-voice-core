#!/bin/bash
# Voice Core macOS Native Startup Script with MPS (Metal Performance Shaders) GPU Support
# This script runs the application natively on macOS (not in Docker) for full GPU access

set -e

echo "🚀 Voice Core - macOS Native Launcher"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running on macOS
if [[ "$OSTYPE" != "darwin"* ]]; then
    echo -e "${RED}Error: This script is designed for macOS only.${NC}"
    echo "For Linux with NVIDIA GPU, use: docker compose up -d"
    exit 1
fi

# Check for Apple Silicon
ARCH=$(uname -m)
if [[ "$ARCH" == "arm64" ]]; then
    echo -e "${GREEN}✓ Apple Silicon (M1/M2/M3) detected${NC}"
    echo -e "${GREEN}✓ Metal Performance Shaders (MPS) will be used for GPU acceleration${NC}"
else
    echo -e "${YELLOW}⚠ Intel Mac detected - GPU acceleration not available${NC}"
fi

# Check Python installation
echo ""
echo "📋 Checking dependencies..."

if ! command -v python3 &> /dev/null; then
    echo -e "${RED}Error: Python 3 is not installed${NC}"
    echo "Install with: brew install python"
    exit 1
fi

PYTHON_VERSION=$(python3 --version 2>&1 | awk '{print $2}')
echo -e "${GREEN}✓ Python version: $PYTHON_VERSION${NC}"

# Check if virtual environment exists
VENV_DIR=".venv"
if [ ! -d "$VENV_DIR" ]; then
    echo ""
    echo "📦 Creating virtual environment..."
    python3 -m venv "$VENV_DIR"
fi

# Activate virtual environment
echo "📦 Activating virtual environment..."
source "$VENV_DIR/bin/activate"

# Upgrade pip
echo "📦 Upgrading pip..."
pip install --quiet --upgrade pip

# Install requirements
echo "📦 Installing dependencies..."
pip install --quiet -r requirements.txt

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p data/models data/outputs data/profiles data/uploads logs

# Check PyTorch MPS availability
echo ""
echo "🔍 Checking PyTorch and GPU support..."
python3 << 'EOF'
import torch
import platform

print(f"PyTorch version: {torch.__version__}")
print(f"macOS version: {platform.mac_ver()[0]}")

if torch.backends.mps.is_available():
    print("✅ MPS (Metal Performance Shaders) is available!")
    print(f"   GPU: Apple M-Series")
    # Test MPS
    device = torch.device("mps")
    test_tensor = torch.randn(100, 100).to(device)
    print("✅ MPS test passed")
else:
    print("⚠️  MPS not available, will use CPU")
    if platform.machine() == 'arm64':
        print("   Tip: Install PyTorch with MPS support:")
        print("   pip install torch torchvision torchaudio")
EOF

# Load environment variables
if [ -f ".env" ]; then
    echo ""
    echo "📋 Loading environment from .env..."
    set -a
    source .env
    set +a
else
    echo -e "${YELLOW}⚠ .env file not found, using defaults${NC}"
fi

# Set macOS-specific environment variables
export USE_GPU=${USE_GPU:-auto}
export PYTORCH_ENABLE_MPS_FALLBACK=${PYTORCH_ENABLE_MPS_FALLBACK:-1}

# Show configuration
echo ""
echo "⚙️  Configuration:"
echo "   USE_GPU: $USE_GPU"
echo "   API_PORT: ${API_PORT:-5001}"
echo "   UI_PORT: ${UI_PORT:-5002}"

# Start the application
echo ""
echo "🚀 Starting Voice Core API..."
echo "   API will be available at: http://localhost:${API_PORT:-5001}"
echo ""

# Trap Ctrl+C to deactivate virtual environment on exit
trap 'echo -e "\n🛑 Shutting down..."; deactivate 2>/dev/null || true; exit 0' INT

# Start the API
python3 main.py
