"""
Start both API (5001) and UI (5002) servers simultaneously.
"""
import sys
import time
import subprocess
import os
import signal
from loguru import logger

def kill_processes_on_ports(ports):
    """Find and kill processes listening on specified ports."""
    try:
        ports_str = ",".join(map(str, ports))
        cmd = f"lsof -ti:{ports_str}"
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
        
        if result.returncode == 0 and result.stdout.strip():
            # Filter and deduplicate PIDs
            pids = list(set(filter(None, result.stdout.strip().split('\n'))))
            for pid_str in pids:
                try:
                    pid = int(pid_str)
                    if pid == os.getpid(): continue
                    logger.warning(f"Killing zombie process {pid} on ports {ports_str}...")
                    os.kill(pid, signal.SIGKILL)
                except (ValueError, ProcessLookupError, PermissionError) as e:
                    logger.debug(f"Could not kill {pid_str}: {e}")
            time.sleep(1.5) # Slight increase to ensure port release
    except Exception as e:
        logger.error(f"Error checking ports: {e}")

def start_servers():
    commands = [
        ("API Server", [sys.executable, "main.py"]),
        ("UI Server", [sys.executable, "frontend_server.py"])
    ]
    
    # Kill any existing processes on our ports before starting
    kill_processes_on_ports([5001, 5002])
    
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
