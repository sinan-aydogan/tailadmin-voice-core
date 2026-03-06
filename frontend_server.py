"""
Frontend development server for static files.
Serves static UI files on port 5002.
"""
import os
import sys
import http.server
import socketserver
from loguru import logger
from app.config import settings

# Move to the static directory so the server root is the static folder
static_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "static")
if not os.path.exists(static_dir):
    os.makedirs(static_dir)

os.chdir(static_dir)

PORT = settings.UI_PORT

Handler = http.server.SimpleHTTPRequestHandler

if __name__ == "__main__":
    socketserver.TCPServer.allow_reuse_address = True
    with socketserver.TCPServer(("", PORT), Handler) as httpd:
        logger.info(f"Serving UI at http://localhost:{PORT}")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            logger.info("Shutting down UI server.")
            sys.exit(0)
