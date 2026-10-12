#!/usr/bin/env python3
"""Package Excel validation compatibility/messages without replacing host data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import sys
from zip_permissions import write_entries

root = Path(__file__).resolve().parents[1]
target = Path(sys.argv[1]).resolve()
target.mkdir(parents=True, exist_ok=False)
entries = {
    'jmac_app/app/Domain/Catalog/ExcelCatalog.php': (root / 'app/Domain/Catalog/ExcelCatalog.php').read_bytes(),
    'jmac_app/docs/EXCEL_ERROR_DETALLADO.md': (root / 'docs/EXCEL_ERROR_DETALLADO.md').read_bytes(),
    'LEEME_CORRECCION_EXCEL.txt': (root / 'docs/EXCEL_ERROR_DETALLADO.md').read_bytes(),
}
checksums = {name: hashlib.sha256(data).hexdigest() for name, data in entries.items()}
entries['CHECKSUMS_CORRECCION_EXCEL_SHA256.txt'] = ''.join(digest + '  ' + name + '\n' for name, digest in sorted(checksums.items())).encode()
archive = target / 'JMAC-correccion-Excel.zip'
with ZipFile(archive, 'x', ZIP_DEFLATED, compresslevel=9) as output:
    write_entries(output, entries)
report = {'archive': archive.name, 'bytes': archive.stat().st_size,
          'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
          'payload_files': len(entries), 'file_checksums': checksums,
          'no_javascript_files': True, 'no_env_storage_database_vendor_assets_or_ini': True,
          'migrations': 0, 'hosting_applied': False, 'owner_original_workbook_tested': False}
(target / 'EVIDENCIA.json').write_text(json.dumps(report, indent=2) + '\n')
print(json.dumps({key: value for key, value in report.items() if key != 'file_checksums'}))
