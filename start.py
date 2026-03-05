"""
Start both API (5001) and UI (5002) servers simultaneously.
"""
import sys
import time
import subprocess
from loguru import logger

def start_servers():
    commands = [
        ("API Server", [sys.executable, "main.py"]),
        ("UI Server", [sys.executable, "frontend_server.py"])
    ]
    
    processes = []
    
    try:
        for name, cmd in commands:
            logger.info(f"Starting {name}...")
            p = subprocess.Popen(cmd)
            processes.append((name, p))
            time.sleep(1)  # Stagger startup
            
        logger.success("Both servers started. Press Ctrl+C to stop.")
        
        # Keep main thread alive
        while True:
            time.sleep(1)
            
    except KeyboardInterrupt:
        logger.info("\nShutting down servers...")
        for name, p in processes:
            logger.info(f"Terminating {name}...")
            p.terminate()
            p.wait()
        logger.success("All servers stopped successfully.")
        sys.exit(0)

if __name__ == "__main__":
    start_servers()
