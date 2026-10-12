#!/usr/bin/env python3
"""Package the catalogue spacing correction without touching data or JS."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import sys
from zip_permissions import write_entries

root = Path(__file__).resolve().parents[1]
destination = Path(sys.argv[1]).resolve()
destination.mkdir(parents=True, exist_ok=False)
manifest_bytes = (root / 'public/build/manifest.json').read_bytes()
manifest = json.loads(manifest_bytes)
js = manifest['resources/js/app.js']['file']
assert js == 'assets/app-B9eZnYus.js', 'Review a changed JavaScript build before packaging'
assert hashlib.sha256((root / 'public/build' / js).read_bytes()).hexdigest() == 'aeef49f861eb9f5be65dfcf28f7c1e594b59853f348441d4cba0fce416a9b18d'
css = manifest['resources/css/app.css']['file']
entries = {}
for name in ['page.blade.php', 'catalog.blade.php']:
    entries['jmac_app/resources/views/site/' + name] = (root / 'resources/views/site' / name).read_bytes()
for prefix in ['jmac_app/public', 'public_html']:
    entries[prefix + '/build/manifest.json'] = manifest_bytes
    entries[prefix + '/build/' + css] = (root / 'public/build' / css).read_bytes()
entries['LEEME_AJUSTE_CATALOGO.txt'] = (root / 'docs/AJUSTE_CATALOGO.md').read_bytes()
checksums = {name: hashlib.sha256(data).hexdigest() for name, data in entries.items()}
entries['CHECKSUMS_AJUSTE_CATALOGO_SHA256.txt'] = ''.join(digest + '  ' + name + '\n' for name, digest in sorted(checksums.items())).encode()
archive_path = destination / 'JMAC-ajuste-espacio-catalogo.zip'
with ZipFile(archive_path, 'x', ZIP_DEFLATED, compresslevel=9) as archive:
    write_entries(archive, entries)
metadata = {'archive': archive_path.name, 'bytes': archive_path.stat().st_size,
            'sha256': hashlib.sha256(archive_path.read_bytes()).hexdigest(),
            'file_checksums': checksums, 'file_permissions': '0644',
            'directory_permissions': '0755', 'javascript_in_archive': False,
            'migrations': 0, 'database_changes': False, 'hosting_applied': False}
(destination / 'EVIDENCIA.json').write_text(json.dumps(metadata, indent=2) + '\n')
print(json.dumps({key: value for key, value in metadata.items() if key != 'file_checksums'}))
