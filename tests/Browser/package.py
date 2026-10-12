from pathlib import Path
from playwright.sync_api import sync_playwright
import json,os,subprocess,secrets,base64,hmac,hashlib,struct,time,urllib.request,shutil
root=Path('/workspace/jmaccr');release=Path(os.environ.get('JMAC_PACKAGE_ROOT','/workspace/releases/JMAC-2.0-CPANEL-REVISION-2026-10-10'));app=release/'jmac_app';db='jmac_release_full_test';created=False;server=None;log=None
public_alternative=release/('public_html' if (release/'public_html').exists() else 'archivos_publicos')
runtime=json.loads((app/'vendor/composer/installed.json').read_text());assert runtime['dev'] is False
release_excel='openspout/openspout' in [package['name'] for package in runtime['packages']]
assert not (app/'.env').exists()
email='package-qa@example.test';password=secrets.token_hex(24)+'!Aa7'
environment=dict(os.environ,APP_ENV='staging',DB_CONNECTION='mysql',DB_DATABASE=db,DB_USERNAME='agent',DB_SOCKET='/workspace/toolchain/mysql.sock',SESSION_SECURE_COOKIE='false',APP_DEBUG='false',MAIL_MAILER='array')
try:
 subprocess.run(['php','-r','$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");if($p->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=\'jmac_release_full_test\'")->fetch())throw new LogicException("Existing DB; refuse");$p->exec("CREATE DATABASE jmac_release_full_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'],check=True,cwd=app,env=environment);created=True
 env=(app/'.env.cpanel.example').read_text().replace('APP_URL=https://SUBDOMINIO_DE_PRUEBAS','APP_URL=https://staging.example.test').replace('DB_DATABASE=','DB_DATABASE='+db).replace('DB_USERNAME=','DB_USERNAME=agent').replace('SESSION_SECURE_COOKIE=true','SESSION_SECURE_COOKIE=false')+'\nDB_SOCKET=/workspace/toolchain/mysql.sock\n';(app/'.env').write_text(env);(app/'.env').chmod(0o600)
 owner=app/'storage/app/private/owner-setup.json';owner.write_text(json.dumps({'name':'Propietario paquete QA','email':email,'password':password}));owner.chmod(0o600)
 subprocess.run(['php','artisan','package:discover'],check=True,cwd=app,env=environment,stdout=subprocess.DEVNULL)
 subprocess.run(['php','artisan','jmac:install-staging'],check=True,cwd=app,env=environment,stdout=subprocess.DEVNULL);assert not owner.exists()
 subprocess.run(['php','artisan','jmac:install-staging'],check=True,cwd=app,env=environment,stdout=subprocess.DEVNULL)
 empty=subprocess.check_output(['php','artisan','jmac:verify-empty'],cwd=app,env=environment,text=True);assert 'products: 0' in empty
 results=[]
 with sync_playwright() as p:
  browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
  for folder in [app/'public',public_alternative]:
   log=open('/tmp/jmac-package-server.log','w');server=subprocess.Popen(['php','-S','127.0.0.1:8092','-t',str(folder),str(app/'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],cwd=folder,env=environment,stdout=log,stderr=log)
   for attempt in range(40):
    try:urllib.request.urlopen('http://127.0.0.1:8092/admin/login');break
    except Exception:time.sleep(.1)
   context=browser.new_context(viewport={'width':1440,'height':1000});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   for route in ['/','/nosotros/','/contacto/','/catalogo/','/novedades/','/robots.txt','/sitemap.xml']:
    response=page.goto('http://127.0.0.1:8092'+route);assert response.status==200,(route,response.status)
   for route in ['/.env','/vendor/autoload.php','/storage/logs/laravel.log','/.git/config']:
    response=page.request.get('http://127.0.0.1:8092'+route);assert response.status in [403,404],(route,response.status)
   page.goto('http://127.0.0.1:8092/admin/login');page.fill('#email',email);page.fill('#password',password);page.click('button:has-text("Iniciar sesión")')
   if folder==app/'public':
    page.wait_for_url('**/admin/mfa/enroll');secret=page.locator('.secret').inner_text().strip();digest=hmac.new(base64.b32decode(secret+'='*((8-len(secret)%8)%8)),struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();off=digest[-1]&15;code=str((struct.unpack('>I',digest[off:off+4])[0]&0x7fffffff)%1000000).zfill(6);page.fill('#code',code);page.click('button:has-text("Verificar")');page.wait_for_selector('text=Segundo factor activado');recovery=page.locator('li code').first.inner_text();page.click('a:has-text("Continuar al panel")')
   else:
    page.wait_for_url('**/admin/mfa/challenge');page.fill('#code',recovery);page.click('button:has-text("Verificar")')
   page.wait_for_url('**/admin');assert page.locator('.metric').all_inner_texts()==['0']*4
   admin_routes=['/admin/pages','/admin/theme','/admin/menus','/admin/media','/admin/catalog/categories','/admin/catalog/brands','/admin/catalog/products','/admin/preorders','/admin/contacts','/admin/notifications','/admin/posts','/admin/seo','/admin/users','/admin/settings','/admin/audit','/admin/backups','/admin/diagnostics']
   if release_excel:admin_routes.append('/admin/catalog/imports')
   for route in admin_routes:
    response=page.goto('http://127.0.0.1:8092'+route);assert response.status==200,(route,response.status)
   assert not errors,errors
   results.append({'public_layout':folder.name,'public_routes_200':7,'private_routes_denied':4,'admin_screens_200':len(admin_routes),'login_mfa':True,'initial_counts':[0]*4,'javascript_errors':errors})
   context.close();server.terminate();server.wait();server=None;log.close();log=None
  browser.close()
 report='production-full-package-smoke.json' if release_excel else 'final-package-smoke.json'
 (root/'docs/evidence'/report).write_text(json.dumps({'result':'PASSED','runtime_packages':len(runtime['packages']),'no_node_composer_required_at_runtime':True,'installation_idempotent':True,'seed':empty.strip().splitlines(),'layouts':results,'environment':'local PHP 8.4/MariaDB; no access to owner cPanel'},indent=2)+'\n')
 print('Paquete runtime: instalación limpia, MFA, pantallas admin, dos layouts y rutas privadas: PASSED')
finally:
 if server:server.terminate();server.wait()
 if log:log.close()
 if created:subprocess.run(['php','-r','$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");$p->exec("DROP DATABASE jmac_release_full_test");'],check=True,cwd=app,env=environment)
 if (app/'.env').exists():(app/'.env').unlink()
 for path in (app/'storage').rglob('*'):
  if path.is_file():path.unlink()
 for path in (app/'bootstrap/cache').glob('*.php'):path.unlink()
