#!/usr/bin/env python3
"""Repair compiled CSS/logo ZIP permissions without touching data or JS."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
import hashlib
import json
import sys
from zip_permissions import write_entries

root = Path(__file__).resolve().parents[1]
destination = Path(sys.argv[1]).resolve()
destination.mkdir(parents=True, exist_ok=False)
manifest_bytes = (root / 'public/build/manifest.json').read_bytes()
manifest = json.loads(manifest_bytes)
css = manifest['resources/css/app.css']['file']
assert css == 'assets/app-O1-jpzL0.css', 'Review new builds before packaging'
assert manifest['resources/js/app.js']['file'] == 'assets/app-B9eZnYus.js'
entries = {}
for prefix in ['public_html', 'jmac_app/public']:
    entries[f'{prefix}/build/{css}'] = (root / 'public/build' / css).read_bytes()
    entries[f'{prefix}/build/manifest.json'] = manifest_bytes
    entries[f'{prefix}/brand/jmac-logo.png'] = (root / 'public/brand/jmac-logo.png').read_bytes()
assert hashlib.sha256(entries['public_html/brand/jmac-logo.png']).hexdigest() == 'a339683a62c4448e5e7ca594e83744c9d65082bafcd0332363c9ec9aebf9651d'
entries['LEEME_REPARACION_ESTILOS.txt'] = (root / 'docs/REPARACION_ESTILOS_LOGO.md').read_bytes()
checksums = {name: hashlib.sha256(content).hexdigest() for name, content in entries.items()}
entries['CHECKSUMS_REPARACION_SHA256.txt'] = ''.join(f'{digest}  {name}\n' for name, digest in sorted(checksums.items())).encode()
archive_path = destination / 'JMAC-reparacion-estilos-logo.zip'
with ZipFile(archive_path, 'x', ZIP_DEFLATED, compresslevel=9) as archive:
    write_entries(archive, entries)
metadata = {'archive': archive_path.name, 'bytes': archive_path.stat().st_size,
            'sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest(),
            'file_checksums': checksums, 'file_permissions': '0644',
            'directory_permissions': '0755', 'javascript_in_archive': False,
            'runtime_code_changes': False, 'migrations': 0,
            'hosting_applied': False, 'hosting_antivirus_acceptance': 'NOT_VERIFIED'}
(destination / 'EVIDENCIA.json').write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({key: value for key, value in metadata.items() if key != 'file_checksums'}, indent=2))
