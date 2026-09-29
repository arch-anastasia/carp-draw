#!/usr/bin/env python3
"""Exercise the real Nginx config against a tiny PHP front-controller fixture.

A Laravel HTTP test cannot catch Nginx returning 404 around a valid robots body.
Uses only Docker + Python's standard library; creates isolated, ephemeral services.
"""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

root = Path(__file__).resolve().parent.parent
config_path = Path(sys.argv[1]) if len(sys.argv) > 1 else root / 'docker/nginx/default.conf'
project = f'carpdraw-nginx-check-{os.getpid()}'
with tempfile.TemporaryDirectory(prefix='carpdraw-nginx-') as directory:
    temp = Path(directory)
    public = temp / 'public'
    public.mkdir()
    (public / 'build').mkdir()
    (public / 'build/app-deadbeef.js').write_text('/* hashed */')
    (public / 'logo.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg"/>')
    (public / 'index.php').write_text('<?php header("Content-Type: text/plain"); echo "User-agent: *\\nAllow: /\\n";')
    (temp / 'default.conf').write_text(config_path.read_text())
    # FPM/nginx drop privileges, so the fixture parent must be traversable.
    temp.chmod(0o755)
    compose = {
        'services': {
            'app': {'image': 'php:8.4-fpm-alpine', 'volumes': [f'{public}:/var/www/html/public:ro']},
            'nginx': {
                'image': 'nginx:alpine', 'depends_on': ['app'],
                'ports': ['127.0.0.1::80'],
                'volumes': [f'{public}:/var/www/html/public:ro', f'{temp}/default.conf:/etc/nginx/conf.d/default.conf:ro'],
            },
        },
    }
    compose_path = temp / 'compose.json'
    compose_path.write_text(json.dumps(compose))
    command = ['docker', 'compose', '-p', project, '-f', str(compose_path)]
    try:
        subprocess.run(command + ['up', '-d'], check=True, stdout=subprocess.DEVNULL)
        address = subprocess.check_output(command + ['port', 'nginx', '80'], text=True).strip()
        origin = 'http://' + address
        for attempt in range(30):
            try:
                with urllib.request.urlopen(origin + '/robots.txt', timeout=3) as response:
                    assert response.status == 200
                    assert b'User-agent:' in response.read()
                break
            except urllib.error.HTTPError as error:
                if error.code != 502:
                    raise AssertionError(f'robots.txt returned {error.code}, expected 200') from error
                time.sleep(0.2)
            except urllib.error.URLError:
                time.sleep(0.2)
        else:
            raise AssertionError('Nginx/PHP did not become ready')
        for path, immutable in [('/build/app-deadbeef.js', True), ('/logo.svg', False)]:
            with urllib.request.urlopen(origin + path, timeout=3) as response:
                assert ('immutable' in ','.join(response.headers.get_all('Cache-Control'))) == immutable
        try:
            urllib.request.urlopen(origin + '/build/missing.js', timeout=3)
            raise AssertionError('Missing assets must return 404')
        except urllib.error.HTTPError as error:
            assert error.code == 404
        print('Nginx: robots 200; hashed assets immutable; stable assets refresh; missing assets 404.')
    finally:
        subprocess.run(command + ['down', '--volumes'], check=False, stdout=subprocess.DEVNULL)
