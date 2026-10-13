#!/usr/bin/env python3
"""Ship visible JS separately from the cPanel PHP/views/CSS update."""
from pathlib import Path
from datetime import datetime, timezone
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib,json,sys
from zip_permissions import write_entries

root=Path(__file__).resolve().parents[1];target=Path(sys.argv[1]).resolve();target.mkdir(parents=True,exist_ok=False)
files=['app/Http/Controllers/RequestController.php','app/Domain/Sales/Privacy.php','app/Domain/Sales/Requests.php','app/Domain/Catalog/Catalog.php','app/Domain/Site/Site.php','resources/views/layouts/site.blade.php','resources/views/site/product-card.blade.php','resources/views/site/product.blade.php','resources/views/site/product-order.blade.php','resources/views/site/contact.blade.php','resources/views/site/preorder-form.blade.php','resources/views/site/privacy.blade.php','resources/views/admin/settings.blade.php','resources/views/admin/sale-detail.blade.php','docs/PRIVACIDAD_Y_CANTIDAD.md']
entries={'jmac_app/'+name:(root/name).read_bytes() for name in files}
manifest=json.loads((root/'public/build/manifest.json').read_text());css=manifest['resources/css/app.css']['file'];js=manifest['resources/js/app.js']['file']
assert Path(js).name=='app-CsHK9iZX.js','Update the reviewed manual for a changed build'
for prefix in ['jmac_app/public','public_html']:
    entries[prefix+'/build/manifest.json']=(root/'public/build/manifest.json').read_bytes()
    entries[prefix+'/build/'+css]=(root/'public/build'/css).read_bytes()
entries['LEEME_PRIVACIDAD_CANTIDADES.txt']=(root/'docs/PRIVACIDAD_Y_CANTIDAD.md').read_bytes()
checksums={name:hashlib.sha256(data).hexdigest() for name,data in entries.items()}
js_data=(root/'public/build'/js).read_bytes();js_sha=hashlib.sha256(js_data).hexdigest()
for prefix in ['jmac_app/public','public_html']:checksums[prefix+'/build/'+js]=js_sha
entries['CHECKSUMS_PRIVACIDAD_CANTIDADES_SHA256.txt']=''.join(digest+'  '+name+'\n' for name,digest in sorted(checksums.items())).encode()
archive=target/'JMAC-privacidad-cantidades.zip'
with ZipFile(archive,'x',ZIP_DEFLATED,compresslevel=9) as output:write_entries(output,entries,date_time=datetime.now(timezone.utc).timetuple()[:6])
(target/Path(js).name).write_bytes(js_data);(target/Path(js).name).chmod(0o644)
report={'archive':archive.name,'bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'payload_files':len(entries),'file_checksums':checksums,'javascript':Path(js).name,'javascript_bytes':len(js_data),'javascript_sha256':js_sha,'css':css,'archive_javascript_files':0,'no_env_storage_database_vendor_or_ini':True,'migrations':0,'hosting_applied':False,'hosting_antivirus_tested':False}
(target/'EVIDENCIA.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({key:value for key,value in report.items() if key!='file_checksums'}))
