from playwright.sync_api import sync_playwright
from pathlib import Path
import json,hmac,hashlib,base64,struct,time,os
origin=os.environ.get('JMAC_TEST_ORIGIN','http://127.0.0.1:8081')
credentials=json.loads(Path('/tmp/jmac-browser-login.json').read_text())
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':1440,'height':1000})
 page.goto(origin+'/admin/login');page.screenshot(path='/workspace/jmaccr/docs/evidence/h1-login-desktop.png',full_page=True)
 page.fill('#email',credentials['email']);page.fill('#password',credentials['password']);page.click('button:has-text("Iniciar sesión")');page.wait_for_url('**/admin/mfa/enroll')
 secret=page.locator('.secret').inner_text().strip();step=int(time.time())//30
 digest=hmac.new(base64.b32decode(secret+'='*((8-len(secret)%8)%8)),struct.pack('>Q',step),hashlib.sha1).digest();o=digest[-1]&15;code=str((struct.unpack('>I',digest[o:o+4])[0]&0x7fffffff)%1000000).zfill(6)
 page.fill('#code',code);page.click('button:has-text("Verificar")');page.wait_for_selector('text=Segundo factor activado')
 recovery=page.locator('li code').first.inner_text();page.click('a:has-text("Continuar al panel")');page.wait_for_url('**/admin')
 assert page.locator('.metric').all_inner_texts()==['0','0','0','0']
 page.screenshot(path='/workspace/jmaccr/docs/evidence/h1-dashboard-desktop.png',full_page=True)
 page.click('a:has-text("Revisar borradores")');assert page.locator('tbody tr').count()==5
 page.screenshot(path='/workspace/jmaccr/docs/evidence/h1-drafts-desktop.png',full_page=True)
 page.goto(origin+'/admin');page.click('button:has-text("Cerrar sesión")');page.fill('#email',credentials['email']);page.fill('#password',credentials['password']);page.click('button:has-text("Iniciar sesión")');page.wait_for_url('**/admin/mfa/challenge');page.fill('#code',recovery);page.click('button:has-text("Verificar")');page.wait_for_url('**/admin')
 for w in [360,390,768,1024,1440]:
  page.set_viewport_size({'width':w,'height':900});page.reload();assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),w
 page.set_viewport_size({'width':390,'height':844});page.screenshot(path='/workspace/jmaccr/docs/evidence/h1-dashboard-mobile.png',full_page=True)
 for path in ['/.env','/vendor/autoload.php','/storage/logs/laravel.log','/.git/config']:
  response=page.request.get(origin+path);assert response.status in [403,404],(path,response.status)
 print('Browser: login, MFA enrollment, recovery login, dashboard 0, five drafts, 5 widths without overflow, 4 private paths denied (403 or 404): PASSED')
 browser.close()
Path('/tmp/jmac-browser-login.json').unlink()
