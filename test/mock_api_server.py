#!/usr/bin/env python3
"""
Mock API Server pour tester le module SMS Android Dolibarr
Usage: python3 mock_api_server.py [port]
"""
import json
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
USER = "sms"
PASS = ""

class MockSmsGatewayHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        content_len = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(content_len).decode('utf-8')

        # Basic Auth check
        auth = self.headers.get('Authorization', '')
        import base64
        expected = base64.b64encode(f"{USER}:{PASS}".encode()).decode()
        auth_ok = auth == f"Basic {expected}"

        print(f"\n--- REQUETE RECUE ---")
        print(f"Path: {self.path}")
        print(f"Auth OK: {auth_ok}")
        print(f"Body: {body}")
        print(f"--------------------")

        if not auth_ok:
            self.send_response(401)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"error": "Unauthorized"}).encode())
            return

        if self.path == '/messages':
            try:
                data = json.loads(body)
                phone = data.get('phoneNumbers', ['?'])[0]
                msg = data.get('message', '')

                # Simule une reponse succes
                response = {
                    "id": "mock-" + str(hash(phone + msg))[-8:],
                    "state": "Sent",
                    "recipients": [
                        {
                            "status": "Sent",
                            "phoneNumber": phone
                        }
                    ]
                }
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps(response).encode())
                print(">>> REPONSE: 200 Sent (simule unenvoi reussi)")
            except Exception as e:
                self.send_response(400)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps({"error": str(e)}).encode())
        else:
            self.send_response(404)
            self.end_headers()

    def do_GET(self):
        # Pour un test rapide
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({"status": "mock server running"}).encode())

    def log_message(self, format, *args):
        pass  # Silence le log HTTP standard

if __name__ == '__main__':
    server = HTTPServer(('0.0.0.0', PORT), MockSmsGatewayHandler)
    print(f"Serveur mock SMS Gateway demarre sur le port {PORT}")
    print(f"Authentification: {USER}:{PASS}")
    print(f"URL a configurer dans Dolibarr: http://<IP_DU_NAS>:{PORT}")
    print("Attente des requetes... (Ctrl+C pour arreter)")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nArret du serveur.")
        server.server_close()
