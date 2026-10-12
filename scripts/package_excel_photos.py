#!/usr/bin/env python3
"""Package only the Excel photo workflow; preserve hosting configuration/data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import sys
from zip_permissions import write_entries

root = Path(__file__).resolve().parents[1]
target = Path(sys.argv[1]).resolve()
target.mkdir(parents=True, exist_ok=False)
files = [
    'app/Domain/Catalog/CatalogFileLimits.php', 'app/Domain/Catalog/ExcelPictures.php',
    'app/Domain/Catalog/CatalogImportPhotos.php', 'app/Domain/Catalog/ExcelCatalog.php',
    'app/Domain/Catalog/CatalogImports.php', 'app/Domain/Media/ImageUpload.php',
    'app/Http/Controllers/Admin/CatalogImportController.php',
    'app/Http/Controllers/Admin/SystemController.php', 'app/Http/Middleware/SecurityHeaders.php',
    'bootstrap/app.php', 'routes/admin-catalog.php',
    'resources/views/admin/catalog-import.blade.php', 'resources/views/admin/catalog-import-preview.blade.php',
    'resources/views/errors/catalog-upload-size.blade.php',
    'docs/EXCEL_CON_FOTOGRAFIAS.md', 'docs/IMPORTACION_EXCEL.md', 'docs/ADR_004_EXCEL.md',
]
entries = {'jmac_app/' + name: (root / name).read_bytes() for name in files}
entries['LEEME_EXCEL_CON_FOTOGRAFIAS.txt'] = (root / 'docs/EXCEL_CON_FOTOGRAFIAS.md').read_bytes()
checksums = {name: hashlib.sha256(data).hexdigest() for name, data in entries.items()}
entries['CHECKSUMS_EXCEL_FOTOS_SHA256.txt'] = ''.join(digest + '  ' + name + '\n' for name, digest in sorted(checksums.items())).encode()
archive = target / 'JMAC-Excel-con-fotografias.zip'
with ZipFile(archive, 'x', ZIP_DEFLATED, compresslevel=9) as output:
    write_entries(output, entries)
report = {'archive': archive.name, 'bytes': archive.stat().st_size,
          'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
          'payload_files': len(entries), 'file_checksums': checksums,
          'no_javascript_files': True, 'no_env_storage_database_vendor_or_ini': True,
          'migrations': 0, 'hosting_applied': False, 'owner_original_workbook_tested': False}
(target / 'EVIDENCIA.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps({key: value for key, value in report.items() if key != 'file_checksums'}))
