#!/usr/bin/env python3
"""Produce a transparent cPanel patch; direct assets remain subject to scanning."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
import hashlib
import json
import sys
from zip_permissions import write_entries

root = Path(__file__).resolve().parents[1]
old = Path('/workspace/JMAC-actualizacion-visual.zip')
destination = Path(sys.argv[1]).resolve()
destination.mkdir(parents=True, exist_ok=False)
manifest = json.loads((root / 'public/build/manifest.json').read_text())
asset_name = Path(manifest['resources/js/app.js']['file']).name
asset = root / 'public/build/assets' / asset_name
assert asset_name == 'app-B9eZnYus.js', 'Review a new build before changing the known asset'
entries = {}
with ZipFile(old) as archive:
    assert archive.testzip() is None
    for name in archive.namelist():
        if not name.startswith(('jmac_app/', 'public_html/')) or name.endswith('.js'):
            continue
        if name == 'jmac_app/docs/ACTUALIZACION_VISUAL.md':
            continue
        relative = name.removeprefix('jmac_app/') if name.startswith('jmac_app/') else 'public/' + name.removeprefix('public_html/')
        data = (root / relative).read_bytes()
        assert data == archive.read(name), name
        entries[name] = data
    assert asset.read_bytes() == archive.read('public_html/build/assets/' + asset_name)

manual = (root / 'docs/ACTUALIZACION_POR_PARTES.md').read_bytes()
entries['jmac_app/docs/ACTUALIZACION_POR_PARTES.md'] = manual
entries['LEEME_VISUAL_POR_PARTES.txt'] = manual
checks = {name: hashlib.sha256(data).hexdigest() for name, data in entries.items()}
for prefix in ['jmac_app/public', 'public_html']:
    checks[f'{prefix}/build/assets/{asset_name}'] = hashlib.sha256(asset.read_bytes()).hexdigest()
entries['CHECKSUMS_VISUAL_PARTES_SHA256.txt'] = ''.join(f'{digest}  {name}\n' for name, digest in sorted(checks.items())).encode()
archive_path = destination / 'JMAC-visual-PHP-CSS-logo.zip'
with ZipFile(archive_path, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    write_entries(archive, entries)
(destination / asset_name).write_bytes(asset.read_bytes())
(destination / asset_name).chmod(0o644)
metadata = {'archive': archive_path.name, 'bytes': archive_path.stat().st_size,
            'sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest(),
            'archive_payload_files': len(entries), 'archive_javascript_files': 0,
            'file_permissions': '0644', 'directory_permissions': '0755',
            'javascript': asset_name, 'javascript_bytes': asset.stat().st_size,
            'javascript_sha256': hashlib.sha256(asset.read_bytes()).hexdigest(),
            'original_reviewed_runtime_unchanged': True, 'migrations': 0,
            'antivirus_disabled': False, 'hosting_antivirus_result': 'NOT_VERIFIED',
            'windows_antivirus_result': 'NOT_VERIFIED', 'file_checksums': checks}
(destination / 'EVIDENCIA.json').write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: value for key, value in metadata.items() if key != 'file_checksums'}, indent=2))
