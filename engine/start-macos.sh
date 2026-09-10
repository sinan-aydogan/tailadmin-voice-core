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

# Find a Python interpreter >= 3.10 (spaCy/thinc no longer support 3.9).
# Prefer Homebrew's versioned binaries: some PATH entries (e.g. uv's standalone
# builds under ~/.local) report the right version yet cannot build a working venv
# with pip. Importing ensurepip succeeds on those, so that check is not enough —
# we actually create a throwaway venv and confirm its pip runs before accepting.
_venv_works() {
    local py="$1"
    local probe
    probe="$(mktemp -d)" || return 1
    if "$py" -m venv "$probe/v" >/dev/null 2>&1 && "$probe/v/bin/python" -m pip --version >/dev/null 2>&1; then
        rm -rf "$probe"
        return 0
    fi
    rm -rf "$probe"
    return 1
}

PYTHON_BIN=""
for candidate in \
    /opt/homebrew/bin/python3.11 /opt/homebrew/bin/python3.12 \
    /opt/homebrew/bin/python3.13 /opt/homebrew/bin/python3.10 \
    python3.11 python3.12 python3.13 python3.10 python3; do
    command -v "$candidate" &> /dev/null || continue
    ver=$("$candidate" -c 'import sys; print("%d.%d" % sys.version_info[:2])' 2>/dev/null)
    major=${ver%%.*}
    minor=${ver##*.}
    [ "$major" = "3" ] && [ "$minor" -ge 10 ] 2>/dev/null || continue
    # Reject interpreters that can't actually build a working venv (e.g. uv builds).
    _venv_works "$candidate" || continue
    PYTHON_BIN="$candidate"
    break
done

if [ -z "$PYTHON_BIN" ]; then
    echo -e "${RED}Error: Python 3.10+ is required but not found${NC}"
    echo "Install with: brew install python@3.11"
    exit 1
fi

PYTHON_VERSION=$("$PYTHON_BIN" --version 2>&1 | awk '{print $2}')
echo -e "${GREEN}✓ Python version: $PYTHON_VERSION ($PYTHON_BIN)${NC}"

# Set up virtual environment in data/venvies/main
VENV_DIR="data/venvies/main"

# Recreate the venv if it was built with an incompatible (< 3.10) Python
if [ -d "$VENV_DIR" ]; then
    venv_ver=$("$VENV_DIR/bin/python" -c 'import sys; print("%d.%d" % sys.version_info[:2])' 2>/dev/null)
    venv_minor=${venv_ver##*.}
    if [ -z "$venv_ver" ] || [ "${venv_ver%%.*}" != "3" ] || [ "$venv_minor" -lt 10 ] 2>/dev/null; then
        echo -e "${YELLOW}⚠ Existing venv uses Python ${venv_ver:-unknown} (< 3.10); recreating...${NC}"
        rm -rf "$VENV_DIR"
    fi
fi

if [ ! -d "$VENV_DIR" ]; then
    echo ""
    echo "📦 Creating virtual environment in $VENV_DIR..."
    "$PYTHON_BIN" -m venv "$VENV_DIR"
fi

# Activate virtual environment
echo "📦 Activating virtual environment..."
source "$VENV_DIR/bin/activate"

# Set pip cache and temp directories to project directory to avoid system disk space issues
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
export PIP_CACHE_DIR="$SCRIPT_DIR/.pip-cache"
export TMPDIR="$SCRIPT_DIR/.tmp"
mkdir -p "$PIP_CACHE_DIR" "$TMPDIR"

# Upgrade pip
echo "📦 Upgrading pip..."
pip install --quiet --upgrade pip --cache-dir "$PIP_CACHE_DIR" --no-warn-script-location

# Install requirements
echo "📦 Installing dependencies..."
pip install --quiet -r requirements.txt --cache-dir "$PIP_CACHE_DIR" --no-warn-script-location

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p data/models data/outputs data/profiles data/uploads logs

# Initialize database
echo "🗄️  Initializing database..."
python3 migrate.py

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

# --- Port helpers ---
port_in_use() {
    lsof -Pi :"$1" -sTCP:LISTEN -t 2>/dev/null | grep -q .
}

kill_port() {
    local pids
    pids=$(lsof -Pi :"$1" -sTCP:LISTEN -t 2>/dev/null) || true
    if [ -n "$pids" ]; then
        echo "$pids" | xargs kill -9 2>/dev/null || true
        sleep 1
    fi
}

# Resolve a usable port for a service. On conflict, offer to (k) kill the other
# process, (d) use a different port, or (i) abort. Result -> global RESOLVED_PORT.
# A different port propagates everywhere because API_PORT/UI_PORT are exported and
# the frontend learns the API port dynamically via /config.js.
resolve_port() {
    local name=$1
    RESOLVED_PORT=$2

    if ! command -v lsof &> /dev/null; then
        echo -e "${YELLOW}⚠ lsof not found, skipping port check for $RESOLVED_PORT${NC}"
        return 0
    fi

    while port_in_use "$RESOLVED_PORT"; do
        local holder
        holder=$(lsof -Pi :"$RESOLVED_PORT" -sTCP:LISTEN -t 2>/dev/null | head -1)
        echo -e "${YELLOW}⚠ Port $RESOLVED_PORT ($name) başka bir uygulama tarafından kullanılıyor (PID ${holder:-?})${NC}"
        echo "   [k] Kullanan uygulamayı kapat   [d] Farklı port kullan   [i] İptal"
        read -p "   Seçiminiz [k/d/i]: " -n 1 -r choice
        echo
        case "$choice" in
            k|K)
                echo "   Port $RESOLVED_PORT üzerindeki işlem kapatılıyor..."
                kill_port "$RESOLVED_PORT"
                if port_in_use "$RESOLVED_PORT"; then
                    echo -e "${RED}   ✗ Port hâlâ meşgul${NC}"
                else
                    echo -e "${GREEN}   ✓ Port $RESOLVED_PORT artık boş${NC}"
                fi
                ;;
            d|D)
                read -p "   $name için yeni port girin (1024-65535): " new_port
                if [[ "$new_port" =~ ^[0-9]+$ ]] && [ "$new_port" -ge 1024 ] && [ "$new_port" -le 65535 ]; then
                    RESOLVED_PORT=$new_port
                    echo -e "${GREEN}   → $name portu $RESOLVED_PORT olarak denenecek${NC}"
                else
                    echo -e "${RED}   Geçersiz port numarası (1024-65535 arası bir sayı girin)${NC}"
                fi
                ;;
            *)
                echo -e "${RED}   İptal edildi${NC}"
                exit 1
                ;;
        esac
    done
    return 0
}

echo ""
echo "🔍 Checking ports..."

# Ports can be pre-defined via env (e.g. API_PORT=5011 UI_PORT=5012 ./start-macos.sh)
API_PORT=${API_PORT:-5001}
UI_PORT=${UI_PORT:-5002}

resolve_port "API Server" "$API_PORT"; API_PORT=$RESOLVED_PORT
resolve_port "UI Server" "$UI_PORT"; UI_PORT=$RESOLVED_PORT

if [ "$API_PORT" = "$UI_PORT" ]; then
    echo -e "${RED}Error: API ve UI aynı portu ($API_PORT) kullanamaz. Farklı portlar seçin.${NC}"
    exit 1
fi

# Export so main.py (settings.API_PORT), frontend_server.py (UI_PORT/API_PORT) and
# the worker all use the resolved ports.
export API_PORT UI_PORT
echo -e "${GREEN}✓ Portlar ayarlandı → API: $API_PORT, UI: $UI_PORT${NC}"

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

# Run the queue worker as its own OS process so heavy model inference (which holds
# the Python GIL) never freezes the API event loop.
export WORKER_MODE=separate

# Start the UI server in background first (it starts faster)
echo ""
echo "🚀 Starting Voice Core UI Server..."
echo "   UI will be available at: http://localhost:$UI_PORT"
echo ""

python3 frontend_server.py &
UI_PID=$!

# Wait a moment for UI to start
sleep 2

# Start the queue worker in a supervisor loop (auto-restarts if it crashes, without
# taking down the API). The API owns the DB schema + WS connections; the worker
# forwards notifications to it over /internal/notify.
echo "🚀 Starting Voice Core Worker (separate process)..."
(
    while true; do
        python3 -m app.queue
        echo -e "${YELLOW}⚠ Worker exited; restarting in 2s...${NC}"
        sleep 2
    done
) &
WORKER_SUPERVISOR_PID=$!

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
    # Stop the worker supervisor first (so it won't relaunch), then the worker child.
    if [ -n "$WORKER_SUPERVISOR_PID" ] && kill -0 $WORKER_SUPERVISOR_PID 2>/dev/null; then
        echo "   Stopping worker..."
        kill $WORKER_SUPERVISOR_PID 2>/dev/null
    fi
    pkill -f "app.queue" 2>/dev/null || true
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
