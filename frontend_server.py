"""
Frontend development server for static files.
Serves static UI files on port 5002 - completely separate from API server.
"""
import os
import sys
import http.server
import socketserver
from functools import partial

# Setup logging first
from loguru import logger

# Move to the static directory so the server root is the static folder
static_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "static")
if not os.path.exists(static_dir):
    os.makedirs(static_dir)

os.chdir(static_dir)

PORT = 5002  # UI port

class CORSRequestHandler(http.server.SimpleHTTPRequestHandler):
    """Custom request handler with CORS support and better performance."""
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=static_dir, **kwargs)
    
    def do_GET(self):
        # Handle favicon.ico request gracefully if file doesn't exist
        if self.path == '/favicon.ico':
            favicon_path = os.path.join(static_dir, 'favicon.ico')
            if not os.path.exists(favicon_path):
                self.send_response(204)  # No Content
                self.end_headers()
                return
        super().do_GET()
    
    def end_headers(self):
        # Add CORS headers for API communication
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', '*')
        super().end_headers()
    
    def do_OPTIONS(self):
        self.send_response(200)
        self.end_headers()
    
    def log_message(self, format, *args):
        # Reduce log noise - only log errors
        log_str = ' '.join(str(a) for a in args)
        if ' 404 ' in log_str or ' 500 ' in log_str:
            logger.warning(f"UI Server: {log_str}")

class ThreadedHTTPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    """Handle each request in a separate thread for better concurrency."""
    allow_reuse_address = True
    daemon_threads = True  # Threads will exit when main thread exits

if __name__ == "__main__":
    logger.info(f"Starting UI Server on http://localhost:{PORT}")
    logger.info(f"Serving static files from: {static_dir}")
    logger.info("UI Server is completely separate from API server (port 5001)")
    
    with ThreadedHTTPServer(("", PORT), CORSRequestHandler) as httpd:
        logger.success(f"UI Server ready at http://localhost:{PORT}")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            logger.info("Shutting down UI server...")
            httpd.shutdown()
            sys.exit(0)
