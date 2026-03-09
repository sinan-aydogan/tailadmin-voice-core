#!/bin/bash
# Voice Core macOS Native Startup Script with MPS (Metal Performance Shaders) GPU Support
# This script runs the application natively on macOS (not in Docker) for full GPU access

set -eo pipefail

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

# Function to create macOS alias/shortcut
create_macos_shortcut() {
    local app_name="Voice Core"
    local app_path="$HOME/Desktop/${app_name}.app"
    local script_dir="$(cd "$(dirname "$0")" && pwd)"
    
    # Check if alias already exists
    if [ -e "$app_path" ]; then
        echo -e "${YELLOW}⚠ '$app_name' already exists on Desktop${NC}"
        read -p "   Do you want to replace it? [y/N]: " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return 0
        fi
        rm -rf "$app_path"
    fi
    
    echo ""
    echo "🎯 Creating macOS shortcut..."
    
    # Create app bundle structure
    mkdir -p "$app_path/Contents/MacOS"
    mkdir -p "$app_path/Contents/Resources"
    
    # Create Info.plist
    cat > "$app_path/Contents/Info.plist" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>VoiceCore</string>
    <key>CFBundleIdentifier</key>
    <string>com.tailadmin.voicecore</string>
    <key>CFBundleName</key>
    <string>Voice Core</string>
    <key>CFBundleDisplayName</key>
    <string>Voice Core</string>
    <key>CFBundleShortVersionString</key>
    <string>1.0.0</string>
    <key>CFBundleVersion</key>
    <string>1</string>
    <key>LSMinimumSystemVersion</key>
    <string>10.15</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>LSUIElement</key>
    <false/>
</dict>
</plist>
EOF
    
    # Create executable script
    cat > "$app_path/Contents/MacOS/VoiceCore" << EOF
#!/bin/bash
# Voice Core Launcher

# Change to script directory
cd "$script_dir"

# Open Terminal and run start-macos.sh with browser auto-open
osascript << 'APPLESCRIPT'
tell application "Terminal"
    do script "cd '$script_dir' && ./start-macos.sh --open-browser"
    activate
end tell
APPLESCRIPT
EOF
    
    chmod +x "$app_path/Contents/MacOS/VoiceCore"
    
    # Try to set a nice icon (using system microphone icon as fallback)
    # Note: Custom icons require .icns files, we'll use a simple approach
    
    echo -e "${GREEN}✓ Shortcut created: $app_path${NC}"
    echo -e "${GREEN}✓ You can now launch Voice Core from your Desktop${NC}"
    
    # Open the Desktop folder to show the new app
    open "$HOME/Desktop"
}

# Check for command line arguments
CREATE_SHORTCUT=false
OPEN_BROWSER=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --create-shortcut)
            CREATE_SHORTCUT=true
            shift
            ;;
        --open-browser)
            OPEN_BROWSER=true
            shift
            ;;
        --help|-h)
            echo "Usage: ./start-macos.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --create-shortcut    Create Desktop shortcut manually"
            echo "  --open-browser       Automatically open browser after startup"
            echo "  --help, -h          Show this help message"
            echo ""
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Create shortcut if requested manually
if [ "$CREATE_SHORTCUT" = true ]; then
    create_macos_shortcut
    exit 0
fi

# Show startup banner
echo "🚀 Voice Core - macOS Native Launcher"
echo "======================================"

# Check if this is the first run (no .venv exists) - ask for shortcut
if [ ! -d ".venv" ]; then
    echo ""
    read -p "🎯 Would you like to create a Desktop shortcut for Voice Core? [Y/n]: " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        create_macos_shortcut
        echo ""
    fi
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

# Bark MPS support - MUST be set before Python starts
export SUNO_ENABLE_MPS=True
export SUNO_USE_GPU=True
export SUNO_OFFLOAD_CPU=False

# Show configuration
echo ""
echo "⚙️  Configuration:"
echo "   USE_GPU: $USE_GPU"
echo "   API_PORT: ${API_PORT:-5001}"
echo "   UI_PORT: ${UI_PORT:-5002}"

# Check if ports are already in use
check_and_kill_port() {
    local port=$1
    local name=$2
    
    # Check if lsof is available
    if ! command -v lsof &> /dev/null; then
        echo -e "${YELLOW}⚠ lsof not found, skipping port check for $port${NC}"
        return 0
    fi
    
    # Check port with error handling
    local pids
    pids=$(lsof -Pi :$port -sTCP:LISTEN -t 2>/dev/null) || true
    
    if [ -n "$pids" ]; then
        echo -e "${YELLOW}⚠ Port $port is already in use by another process${NC}"
        read -p "   Do you want to kill the process using port $port ($name)? [y/N]: " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            local pid=$(echo "$pids" | head -1)
            if [ -n "$pid" ]; then
                echo "   Killing process $pid on port $port..."
                kill -9 $pid 2>/dev/null || true
                sleep 1
                echo -e "${GREEN}   ✓ Port $port is now free${NC}"
            fi
        else
            echo -e "${RED}   ✗ Cannot start $name - port $port is in use${NC}"
            return 1
        fi
    fi
    return 0
}

echo ""
echo "🔍 Checking ports..."

API_PORT=${API_PORT:-5001}
UI_PORT=${UI_PORT:-5002}

if ! check_and_kill_port $API_PORT "API Server"; then
    exit 1
fi

if ! check_and_kill_port $UI_PORT "UI Server"; then
    exit 1
fi

# Double-check ports are free before starting
echo ""
echo "✅ All ports are free, starting servers..."
sleep 1

# Final port cleanup - kill any remaining processes on our ports
echo "🔧 Final port cleanup..."
for port in $API_PORT $UI_PORT; do
    pids=$(lsof -Pi :$port -sTCP:LISTEN -t 2>/dev/null) || true
    if [ -n "$pids" ]; then
        echo "   Force killing processes on port $port: $pids"
        echo "$pids" | xargs kill -9 2>/dev/null || true
        sleep 1
    fi
done

# Start the UI server in background first (it starts faster)
echo ""
echo "🚀 Starting Voice Core UI Server..."
echo "   UI will be available at: http://localhost:$UI_PORT"
echo ""

python3 frontend_server.py &
UI_PID=$!

# Wait a moment for UI to start
sleep 2

# Start the API server
echo "🚀 Starting Voice Core API..."
echo "   API will be available at: http://localhost:$API_PORT"
echo ""

# Open browser if requested (from shortcut)
if [ "$OPEN_BROWSER" = true ]; then
    # Wait for servers to be ready
    echo ""
    echo "🌐 Waiting for servers to start..."
    sleep 3
    
    # Check if UI is ready and open browser
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:$UI_PORT | grep -q "200\|301\|302"; then
        echo "🌐 Opening browser..."
        open "http://localhost:$UI_PORT"
    else
        # Try again after a few seconds
        sleep 3
        open "http://localhost:$UI_PORT"
    fi
    echo ""
fi

# Function to cleanup processes on exit
cleanup() {
    echo -e "\n🛑 Shutting down..."
    if kill -0 $UI_PID 2>/dev/null; then
        echo "   Stopping UI server..."
        kill $UI_PID 2>/dev/null
        wait $UI_PID 2>/dev/null
    fi
    deactivate 2>/dev/null || true
    exit 0
}

# Trap Ctrl+C and other signals
trap cleanup INT TERM EXIT

# Start the API (foreground)
python3 main.py
