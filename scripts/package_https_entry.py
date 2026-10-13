#!/usr/bin/env python3
"""Package production HTTP entry fix without altering hosting data/settings."""
from pathlib import Path
from datetime import datetime, timezone
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json
import sys
from zip_permissions import write_entries

root=Path(__file__).resolve().parents[1]
target=Path(sys.argv[1]).resolve()
target.mkdir(parents=True,exist_ok=False)
entries={
    'jmac_app/app/Http/Middleware/SecurityHeaders.php':(root/'app/Http/Middleware/SecurityHeaders.php').read_bytes(),
    'jmac_app/docs/HTTP_400_HTTPS.md':(root/'docs/HTTP_400_HTTPS.md').read_bytes(),
    'LEEME_HTTP_400.txt':(root/'docs/HTTP_400_HTTPS.md').read_bytes(),
}
checksums={name:hashlib.sha256(data).hexdigest() for name,data in entries.items()}
entries['CHECKSUMS_HTTP_400_SHA256.txt']=''.join(digest+'  '+name+'\n' for name,digest in sorted(checksums.items())).encode()
archive=target/'JMAC-correccion-HTTP-400.zip'
with ZipFile(archive,'x',ZIP_DEFLATED,compresslevel=9) as output:
    # PHP opcode caches compare file modification times. An update must not
    # retain the same fixed ZIP timestamp as the previous installed PHP file.
    write_entries(output,entries,date_time=datetime.now(timezone.utc).timetuple()[:6])
report={'archive':archive.name,'bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'payload_files':len(entries),'file_checksums':checksums,'no_javascript_files':True,'no_env_storage_database_vendor_assets_or_ini':True,'migrations':0,'hosting_applied':False}
(target/'EVIDENCIA.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({key:value for key,value in report.items() if key!='file_checksums'}))
