#!/bin/bash
# Setup script for Tortoise TTS isolated environment
# This creates a separate virtual environment to avoid dependency conflicts
# Uses symlinks for shared packages (torch, torchaudio, llvmlite) to save ~435MB disk

set -e

echo "🐢 Tortoise TTS Environment Setup"
echo "=================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check Python
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}Error: Python 3 is required${NC}"
    exit 1
fi

PYTHON_VERSION=$(python3 --version 2>&1 | awk '{print $2}')
echo -e "${GREEN}✓ Python version: $PYTHON_VERSION${NC}"

# Directories
VENV_DIR=".venv-tortoise"
MAIN_VENV=".venv"
TORTOISE_SITE="lib/python3.9/site-packages"

# Function to create symlinks for shared packages
create_shared_symlinks() {
    echo ""
    echo "🔗 Creating symlinks for shared packages (saves ~435MB)..."
    
    local TORTOISE_SITE_PATH="$VENV_DIR/$TORTOISE_SITE"
    local MAIN_SITE_PATH="$MAIN_VENV/$TORTOISE_SITE"
    
    # Check if main venv exists
    if [ ! -d "$MAIN_SITE_PATH" ]; then
        echo -e "${YELLOW}⚠ Main .venv not found, skipping symlinks${NC}"
        return
    fi
    
    # Packages to share (name, dist-info suffix)
    local PACKAGES=(
        "torch:torch-2.8.0.dist-info"
        "torchaudio:torchaudio-2.8.0.dist-info"
        "llvmlite:llvmlite-0.43.0.dist-info"
        "torchgen:"
    )
    
    for pkg_info in "${PACKAGES[@]}"; do
        IFS=':' read -r pkg_name dist_info <<< "$pkg_info"
        
        # Remove if exists (not symlink)
        if [ -d "$TORTOISE_SITE_PATH/$pkg_name" ] && [ ! -L "$TORTOISE_SITE_PATH/$pkg_name" ]; then
            rm -rf "$TORTOISE_SITE_PATH/$pkg_name"
        fi
        if [ -n "$dist_info" ] && [ -d "$TORTOISE_SITE_PATH/$dist_info" ] && [ ! -L "$TORTOISE_SITE_PATH/$dist_info" ]; then
            rm -rf "$TORTOISE_SITE_PATH/$dist_info"
        fi
        
        # Create symlink if main package exists and tortoise doesn't have it
        if [ -d "$MAIN_SITE_PATH/$pkg_name" ]; then
            if [ ! -e "$TORTOISE_SITE_PATH/$pkg_name" ]; then
                ln -s "$MAIN_SITE_PATH/$pkg_name" "$TORTOISE_SITE_PATH/$pkg_name"
                echo -e "  ${GREEN}✓${NC} Linked $pkg_name"
            fi
            if [ -n "$dist_info" ] && [ -d "$MAIN_SITE_PATH/$dist_info" ] && [ ! -e "$TORTOISE_SITE_PATH/$dist_info" ]; then
                ln -s "$MAIN_SITE_PATH/$dist_info" "$TORTOISE_SITE_PATH/$dist_info"
            fi
        fi
    done
}

# Create Tortoise environment
if [ -d "$VENV_DIR" ]; then
    echo -e "${YELLOW}⚠ Tortoise environment already exists at $VENV_DIR${NC}"
    read -p "Do you want to recreate it? [y/N]: " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -rf "$VENV_DIR"
    else
        echo "Using existing environment."
        # Try to create symlinks for existing environment
        create_shared_symlinks
        echo ""
        echo -e "${GREEN}✅ Tortoise TTS environment ready!${NC}"
        exit 0
    fi
fi

if [ ! -d "$VENV_DIR" ]; then
    echo ""
    echo "📦 Creating Tortoise virtual environment..."
    python3 -m venv "$VENV_DIR"
fi

# Activate
echo "📦 Activating Tortoise environment..."
source "$VENV_DIR/bin/activate"

# Upgrade pip
echo "📦 Upgrading pip..."
pip install --quiet --upgrade pip

# Install dependencies (skip torch/torchaudio if we can symlink)
if [ -d "$MAIN_VENV/$TORTOISE_SITE/torch" ]; then
    echo "📦 Installing Tortoise-specific dependencies (torch will be symlinked)..."
    # Install tortoise-tts without torch dependencies
    pip install --no-deps tortoise-tts
    # Install other required packages
    pip install transformers scipy numpy numba tokenizers tqdm regex requests huggingface-hub
    pip install psutil  # Required for GPU batch size detection
    
    # Create symlinks
    create_shared_symlinks
else
    echo "📦 Installing Tortoise TTS with all dependencies..."
    pip install torch>=2.1.0 torchaudio>=2.1.0
    pip install tortoise-tts
    pip install psutil
fi

echo ""
echo -e "${GREEN}✅ Tortoise TTS environment setup complete!${NC}"
echo ""
echo "Location: $(pwd)/$VENV_DIR"
echo ""
echo "To use Tortoise:"
echo "  1. Download the Tortoise model from Model Manager"
echo "  2. Select 'tortoise' as TTS engine"
echo "  3. The app will automatically use the isolated environment"
echo ""
