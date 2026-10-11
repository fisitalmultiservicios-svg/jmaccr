"""Exercise the real PHP application in an isolated MariaDB database."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import json
import os
import subprocess
import tempfile
import time
import urllib.request

ROOT = Path(os.environ.get('JMAC_BROWSER_APP_ROOT', '/workspace/jmaccr')).resolve()
PUBLIC = Path(os.environ.get('JMAC_BROWSER_PUBLIC_ROOT', str(ROOT / 'public'))).resolve()
assert (PUBLIC / 'index.php').is_file(), 'A real public entry point is required'
DATABASE = 'jmac_visual_test'
BASE = 'http://127.0.0.1:8095'
EVIDENCE = ROOT / 'docs/evidence'
EVIDENCE.mkdir(exist_ok=True)

with tempfile.TemporaryDirectory(prefix='jmac-visual-browser-') as temporary:
    private = Path(temporary)
    storage = private / 'storage'
    for folder in ['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']:
        (storage / folder).mkdir(parents=True, exist_ok=True)
    environment = dict(os.environ, DB_CONNECTION='mysql', DB_DATABASE=DATABASE,
                       DB_SOCKET='/workspace/toolchain/mysql.sock', DB_USERNAME='agent',
                       DB_PASSWORD='', DB_URL='', APP_ENV='local', APP_DEBUG='false',
                       APP_URL=BASE, SESSION_DRIVER='file', SESSION_SECURE_COOKIE='false',
                       JMAC_MFA_REQUIRED='false', MAIL_MAILER='array', CACHE_STORE='file',
                       LARAVEL_STORAGE_PATH=str(storage), VIEW_COMPILED_PATH=str(storage / 'framework/views'),
                       APP_CONFIG_CACHE=str(private / 'config.php'), BCRYPT_ROUNDS='4')
    credentials_file = private / 'credentials.json'
    credentials_php = r'''require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(config('database.connections.mysql.database')!=='jmac_visual_test')throw new LogicException('Disposable test database required');
    $u=new App\Models\User; $u->name='Propietario QA'; $u->email='visual-owner@example.test';
    $password=bin2hex(random_bytes(24)); $u->password=Illuminate\Support\Facades\Hash::make($password); $u->save();
    $u->roles()->attach(App\Models\Role::where('name','superadmin')->firstOrFail());
    file_put_contents(getenv('JMAC_VISUAL_CREDENTIALS'),json_encode(['email'=>$u->email,'password'=>$password]));
    chmod(getenv('JMAC_VISUAL_CREDENTIALS'),0600);'''
    environment['JMAC_VISUAL_CREDENTIALS'] = str(credentials_file)
    server = None
    created = False
    errors = []
    database_command = r'''$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");
    if($p->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='jmac_visual_test'")->fetch())throw new LogicException("Do not overwrite an existing database");
    $p->exec("CREATE DATABASE jmac_visual_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'''
    with (private / 'server.log').open('w') as log:
        try:
            subprocess.run(['php', '-r', database_command], cwd=ROOT, env=environment, check=True, capture_output=True)
            created = True
            subprocess.run(['php', 'artisan', 'migrate', '--seed', '--force'], cwd=ROOT, env=environment, check=True, capture_output=True)
            subprocess.run(['php', '-r', credentials_php], cwd=ROOT, env=environment, check=True, capture_output=True)
            credentials = json.loads(credentials_file.read_text())
            server = subprocess.Popen(['php', '-S', '127.0.0.1:8095', '-t', str(PUBLIC),
                                       str(ROOT / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
                                      cwd=PUBLIC, env=environment, stdout=log, stderr=log)
            ready = False
            for attempt in range(50):
                try:
                    urllib.request.urlopen(BASE + '/admin/login', timeout=2)
                    ready = True
                    break
                except Exception:
                    time.sleep(.1)
            assert ready, 'PHP server did not become ready'
            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', headless=True, args=['--no-sandbox'])
                context = browser.new_context(viewport={'width': 1440, 'height': 1000})
                public = context.new_page()
                public.on('pageerror', lambda error: errors.append(str(error)))
                public.goto(BASE + '/')
                expect(public.locator('.site-header .site-logo')).to_be_visible()
                assert public.locator('.site-header .site-logo').evaluate('(image) => image.naturalWidth === 679')
                expect(public.locator('.footer-credit')).to_have_text('Creado por fisitalcr')
                expect(public.locator('#cart-drawer')).not_to_be_visible()
                public.locator('[data-cart-open]').click()
                expect(public.locator('#cart-drawer')).to_be_visible()
                expect(public.locator('#cart-drawer')).to_contain_text('Tu carrito está vacío')
                public.keyboard.press('Escape')
                expect(public.locator('#cart-drawer')).not_to_be_visible()
                expect(public.locator('[data-cart-open]')).to_be_focused()
                public.screenshot(path=str(EVIDENCE / 'visual-public-desktop.png'), full_page=True)
                for width in [360, 390, 768, 1024, 1440]:
                    public.set_viewport_size({'width': width, 'height': 900})
                    assert public.evaluate('document.documentElement.scrollWidth <= innerWidth'), ('public', width)
                    public.locator('[data-cart-open]').click()
                    expect(public.locator('#cart-drawer')).to_be_visible()
                    public.keyboard.press('Escape')
                public.set_viewport_size({'width': 390, 'height': 900})
                public.screenshot(path=str(EVIDENCE / 'visual-public-mobile.png'), full_page=True)
                page = context.new_page()
                page.on('pageerror', lambda error: errors.append(str(error)))
                page.goto(BASE + '/admin/login')
                page.fill('#email', credentials['email'])
                page.fill('#password', credentials['password'])
                page.click('button:has-text("Iniciar sesión")')
                page.wait_for_url('**/admin')
                expect(page.locator('h1')).to_have_text('Tu sitio, paso a paso')
                expect(page.locator('.quick-link')).to_have_count(4)
                page.goto(BASE + '/admin/pages/1/edit')
                frame = page.frame_locator('#cms-preview')
                expect(frame.locator('.cms-block').first).to_be_visible()
                frame.locator('.cms-block').nth(1).click()
                expect(page.locator('.block-row.is-selected')).to_have_attribute('data-index', '1')
                expect(page.locator('#block-properties .advanced-options')).not_to_have_attribute('open', '')
                assert not page.locator('[data-field="secondary_label"]').count(), 'Unused fields should not clutter the selected section'
                page.evaluate('window.scrollTo(0, 0)')
                page.screenshot(path=str(EVIDENCE / 'visual-editor-desktop.png'), full_page=True)
                field = page.locator('[data-field="heading"]')
                field.fill('Sección nueva de prueba visual')
                field.press('Tab')
                page.locator('button[form="page-publish-form"]').click()
                expect(page.locator('#cms-status')).to_contain_text('Primero pulsa Guardar borrador')
                page.locator('[data-cms-save]').click()
                expect(page.locator('#cms-status')).to_have_text('Borrador guardado. El público no cambió.')
                public.goto(BASE + '/')
                assert 'Sección nueva de prueba visual' not in public.locator('body').inner_text()
                page.reload()
                page.locator('[data-select="1"]').click()
                expect(page.locator('[data-field="heading"]')).to_have_value('Sección nueva de prueba visual')
                page.locator('#block-type').select_option('heading')
                page.locator('[data-cms-add]').click()
                page.locator('[data-field="heading"]').fill('Bloque nuevo sin estilos')
                page.locator('[data-field="heading"]').press('Tab')
                page.locator('[data-cms-save]').click()
                expect(page.locator('#cms-status')).to_have_text('Borrador guardado. El público no cambió.')
                page.reload()
                page.locator('.block-select').last.click()
                page.locator('#block-properties .advanced-options summary').click()
                page.locator('[data-style="padding"]').fill('44')
                page.locator('[data-style="padding"]').press('Tab')
                page.locator('[data-cms-save]').click()
                expect(page.locator('#cms-status')).to_have_text('Borrador guardado. El público no cambió.')
                page.reload()
                page.locator('.block-select').last.click()
                page.locator('#block-properties .advanced-options summary').click()
                expect(page.locator('[data-style="padding"]')).to_have_value('44')
                for width in [360, 390, 768, 1024, 1440]:
                    page.set_viewport_size({'width': width, 'height': 900})
                    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), ('editor', width)
                page.set_viewport_size({'width': 1440, 'height': 1000})
                page.goto(BASE + '/admin/theme')
                expect(page.locator('.brand-preview-logo')).to_be_visible()
                expect(page.locator('[name="footer_credit"]')).to_have_value('Creado por fisitalcr')
                assert not page.locator('[name="heading_size"]').is_visible()
                page.screenshot(path=str(EVIDENCE / 'visual-theme-desktop.png'), full_page=True)
                page.locator('[name="footer_credit"]').fill('Crédito de prueba visual')
                expect(page.locator('[data-theme-credit]')).to_have_text('Crédito de prueba visual')
                page.locator('.theme-publish button').click()
                expect(page.locator('[data-theme-unsaved]')).to_contain_text('Primero guarda')
                page.locator('[data-theme-form] button[type="submit"], [data-theme-form] button:not([type])').click()
                expect(page.locator('.notice')).to_contain_text('Nuevo borrador visual guardado')
                public.reload()
                expect(public.locator('.footer-credit')).to_have_text('Creado por fisitalcr')
                page.locator('.theme-publish button').click()
                expect(page.locator('.notice')).to_have_text('Diseño publicado.')
                public.reload()
                expect(public.locator('.footer-credit')).to_have_text('Crédito de prueba visual')
                page.goto(BASE + '/admin/catalog/products/create')
                form = page.locator('form[action="/admin/catalog/products"]')
                form.locator('[name="name"]').fill('Producto nuevo QA')
                form.locator('[name="sku"]').fill('VISUAL-QA-001')
                form.locator('[name="slug"]').fill('producto-visual-qa')
                form.locator('[name="status"]').select_option('published')
                form.locator('button:has-text("Guardar producto")').click()
                expect(page.locator('.notice')).to_have_text('Producto guardado.')
                public.goto(BASE + '/catalogo/')
                expect(public.locator('.product-card')).to_have_count(1)
                public.locator('[data-cart-add]').click()
                expect(public.locator('[data-cart-count]')).to_have_text('1')
                public.locator('[data-cart-open]').click()
                expect(public.locator('#cart-drawer .cart-line')).to_contain_text('Producto nuevo QA')
                public.locator('#cart-drawer [data-cart-quantity]').fill('3')
                public.locator('#cart-drawer [data-cart-quantity]').press('Tab')
                expect(public.locator('[data-cart-count]')).to_have_text('3')
                expect(public.locator('#carrito [data-cart-quantity]')).to_have_value('3')
                public.set_viewport_size({'width': 1440, 'height': 1000})
                public.screenshot(path=str(EVIDENCE / 'visual-cart-desktop.png'))
                public.set_viewport_size({'width': 390, 'height': 900})
                public.screenshot(path=str(EVIDENCE / 'visual-cart-mobile.png'))
                public.keyboard.press('Escape')
                public.goto(BASE + '/nosotros/')
                public.locator('[data-cart-open]').click()
                expect(public.locator('#cart-drawer .cart-line')).to_contain_text('VISUAL-QA-001')
                public.locator('#cart-drawer [data-cart-remove]').click()
                expect(public.locator('[data-cart-count]')).to_have_text('0')
                expect(public.locator('#cart-drawer')).to_contain_text('Tu carrito está vacío')
                public.keyboard.press('Escape')
                public.goto(BASE + '/catalogo/')
                public.locator('[data-cart-add]').click()
                public.locator('[data-cart-open]').click()
                public.locator('#cart-drawer a:has-text("Continuar con mi solicitud")').click()
                expect(public.locator('#cart-drawer')).not_to_be_visible()
                assert public.url.endswith('/catalogo/#carrito')
                public.once('dialog', lambda dialog: dialog.accept())
                public.locator('[data-cart-clear]').click()
                expect(public.locator('[data-cart-count]')).to_have_text('0')
                browser.close()
            assert not errors, errors
            result = {'result': 'PASSED', 'logo_clean_asset_loaded': True, 'default_footer_credit': True,
                      'drawer_empty_open_escape_focus': True, 'cart_quantity_remove_persistence': True,
                      'page_click_select_save_reload': True, 'new_block_style_persists': True,
                      'draft_isolation': True, 'unsaved_publication_prevented': True,
                      'theme_credit_preview_save_publish': True, 'responsive_widths': [360, 390, 768, 1024, 1440],
                      'javascript_errors': errors, 'database': DATABASE, 'database_removed': False}
        finally:
            if server:
                server.terminate()
                server.wait(timeout=10)
            if created:
                cleanup = '$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");$p->exec("DROP DATABASE jmac_visual_test");'
                subprocess.run(['php', '-r', cleanup], cwd=ROOT, env=environment, check=True, capture_output=True)
    result['database_removed'] = True
    (EVIDENCE / 'visual-update-browser.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps(result, ensure_ascii=False))
