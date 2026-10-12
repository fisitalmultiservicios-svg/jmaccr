#!/usr/bin/env python3
"""Archive a clean prepared runtime package and verify all payload checksums."""
from pathlib import Path
import hashlib, json, sys, zipfile

root = Path(sys.argv[1]).resolve()
archive = root.parent/(root.name+'.zip')
if not root.is_dir() or archive.exists():
    raise SystemExit('Se requiere directorio preparado y ZIP nuevo; no se sobrescribe.')
app = root/'jmac_app'
installed = json.loads((app/'vendor/composer/installed.json').read_text())
if installed.get('dev') is not False:
    raise SystemExit('Dependencias de desarrollo detectadas; instalar vendor con --no-dev.')
for path in root.rglob('*'):
    if path.is_symlink():
        raise SystemExit('No se permiten enlaces simbólicos: '+str(path.relative_to(root)))
    if path.name in {'.env','owner-setup.json','installation.lock','.git','node_modules','hot','recovery.key'} or path.suffix in {'.log','.sqlite','.sql'}:
        raise SystemExit('Archivo privado/temporal no permitido: '+str(path.relative_to(root)))
for path in (app/'storage').rglob('*'):
    if path.is_file():
        raise SystemExit('Storage debe estar limpio antes de empaquetar.')
if not (app/'public/build/manifest.json').is_file():
    raise SystemExit('Falta build estático.')
checksums = {}
for path in sorted(root.rglob('*')):
    if path.is_file() and path.name != 'CHECKSUMS_SHA256.txt':
        checksums[path.relative_to(root).as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
(root/'CHECKSUMS_SHA256.txt').write_text(''.join(digest+'  '+name+'\n' for name,digest in checksums.items()))
with zipfile.ZipFile(archive,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as output:
    for path in sorted(root.rglob('*')):
        name = path.relative_to(root).as_posix()+('/' if path.is_dir() else '')
        info = zipfile.ZipInfo(name)
        info.create_system = 3
        info.external_attr = ((0o40755 if path.is_dir() else 0o100644)<<16)
        info.compress_type = zipfile.ZIP_DEFLATED
        output.writestr(info,b'' if path.is_dir() else path.read_bytes())
with zipfile.ZipFile(archive) as package:
    assert package.testzip() is None
    for name,digest in checksums.items():
        assert hashlib.sha256(package.read(name)).hexdigest() == digest,name
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix('.zip.sha256').write_text(digest+'  '+archive.name+'\n')
print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'payload_files':len(checksums),'sha256':digest,'crc_and_payload_checksums':'PASSED','runtime_packages':len(installed['packages'])}))
