"""Exercise the real Portainer deploy script against a local HTTP stub."""

import json
import os
from pathlib import Path
import subprocess
import tempfile
import threading
import unittest
from http.server import BaseHTTPRequestHandler, HTTPServer


ROOT = Path(__file__).resolve().parents[2]
IMAGE = 'ghcr.io/nubitio/nubit-erp@sha256:' + 'a' * 64


class DeployTest(unittest.TestCase):
    def test_only_safe_immutable_image_references_are_accepted(self):
        for image, valid in (
            (IMAGE, True),
            ('ghcr.io/nubitio/nubit-erp:latest', False),
            ('ghcr.io/nubitio/nubit-erp@sha256:abc', False),
            ('registry|invalid@sha256:' + 'a' * 64, False),
            ('registry\ninjected@sha256:' + 'a' * 64, False),
        ):
            with self.subTest(image=image):
                result = subprocess.run(
                    ['bash', str(ROOT / 'scripts/ci/validate-image-ref.sh'), image],
                    capture_output=True, text=True,
                )
                self.assertEqual(result.returncode == 0, valid)

    def deploy(self, status=200, rollback=False, get_status=200):
        captured = []

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):
                pass

            def do_GET(self):
                self.send_response(get_status)
                self.end_headers()
                self.wfile.write(json.dumps([
                    {'Id': 1, 'Name': 'prod', 'EndpointId': 2,
                     'Env': [{'name': 'APP_SECRET', 'value': 'preserved'}]},
                ]).encode())

            def do_PUT(self):
                captured.append(json.loads(self.rfile.read(
                    int(self.headers['Content-Length']),
                )))
                self.send_response(status)
                self.end_headers()
                self.wfile.write(b'{"message":"result"}')

        server = HTTPServer(('127.0.0.1', 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            with tempfile.TemporaryDirectory() as directory:
                compose = Path(directory) / 'compose.yml'
                compose.write_text(
                    "services:\n  migrate:\n"
                    "    image: ${IMAGE:-ghcr.io/nubitio/nubit-erp:latest}\n"
                    "    environment:\n      RUN_MIGRATIONS: 'true'\n"
                    "  web:\n"
                    "    image: ${IMAGE:-ghcr.io/nubitio/nubit-erp:latest}\n",
                )
                result = subprocess.run(
                    ['bash', str(ROOT / 'scripts/portainer-deploy.sh')],
                    env={**os.environ,
                         'PORTAINER_URL': f'http://127.0.0.1:{server.server_port}',
                         'PORTAINER_TOKEN': 'test-token', 'STACK_NAME': 'prod',
                         'STACK_ID': '', 'ENDPOINT_ID': '2', 'DRY_RUN': '0',
                         'COMPOSE_FILE': str(compose), 'IMAGE': IMAGE,
                         'RUN_MIGRATIONS': 'false' if rollback else 'true'},
                    capture_output=True, text=True, timeout=15,
                )
                return result, captured
        finally:
            server.shutdown()
            server.server_close()
            thread.join()

    def test_forward_deploy_pins_all_images_and_preserves_environment(self):
        result, payloads = self.deploy()
        self.assertEqual(result.returncode, 0, result.stderr)
        payload = payloads[0]
        self.assertEqual(payload['stackFileContent'].count(IMAGE), 2)
        self.assertIn("RUN_MIGRATIONS: 'true'", payload['stackFileContent'])
        self.assertEqual(payload['env'][0]['value'], 'preserved')

    def test_rollback_skips_migrations(self):
        result, payloads = self.deploy(rollback=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("RUN_MIGRATIONS: 'false'", payloads[0]['stackFileContent'])
        self.assertNotIn("RUN_MIGRATIONS: 'true'", payloads[0]['stackFileContent'])

    def test_failed_stack_update_fails_the_job(self):
        for status in (400, 500):
            with self.subTest(status=status):
                result, _ = self.deploy(status=status)
                self.assertNotEqual(result.returncode, 0)

    def test_failed_stack_lookup_never_updates_stack(self):
        result, payloads = self.deploy(get_status=403)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(payloads, [])


if __name__ == '__main__':
    unittest.main()
