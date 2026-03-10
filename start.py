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
    """Start API and UI servers as completely separate processes."""
    
    # Kill any existing processes on our ports before starting
    kill_processes_on_ports([5001, 5002])
    
    processes = []
    
    try:
        # Start UI Server first (it's lightweight)
        logger.info("Starting UI Server on port 5002...")
        ui_process = subprocess.Popen(
            [sys.executable, "frontend_server.py"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        processes.append(("UI Server", ui_process))
        time.sleep(0.5)  # Short delay for UI to start
        
        # Start API Server (this runs the heavy ML workloads)
        logger.info("Starting API Server on port 5001...")
        api_process = subprocess.Popen(
            [sys.executable, "main.py"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        processes.append(("API Server", api_process))
        time.sleep(1)  # Give API time to initialize
        
        logger.success("=" * 60)
        logger.success("Voice Core is running!")
        logger.success("API Server:  http://localhost:5001")
        logger.success("UI Server:   http://localhost:5002")
        logger.success("=" * 60)
        logger.info("Press Ctrl+C to stop both servers.")
        
        # Monitor processes and keep main thread alive
        while True:
            time.sleep(1)
            # Check if any process died
            for name, p in processes:
                if p.poll() is not None:
                    logger.error(f"{name} exited unexpectedly with code {p.returncode}")
                    # Restart the dead process
                    if name == "API Server":
                        logger.info("Restarting API Server...")
                        api_process = subprocess.Popen(
                            [sys.executable, "main.py"],
                            stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE,
                        )
                        processes[1] = ("API Server", api_process)
                    else:
                        logger.info("Restarting UI Server...")
                        ui_process = subprocess.Popen(
                            [sys.executable, "frontend_server.py"],
                            stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE,
                        )
                        processes[0] = ("UI Server", ui_process)
            
    except KeyboardInterrupt:
        logger.info("\n" + "=" * 60)
        logger.info("Shutting down servers...")
        for name, p in processes:
            logger.info(f"Terminating {name}...")
            p.terminate()
            try:
                p.wait(timeout=5)
            except subprocess.TimeoutExpired:
                logger.warning(f"{name} did not terminate gracefully, forcing...")
                p.kill()
                p.wait()
        logger.success("All servers stopped successfully.")
        sys.exit(0)

if __name__ == "__main__":
    start_servers()
