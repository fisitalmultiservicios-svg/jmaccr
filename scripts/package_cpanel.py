#!/usr/bin/env python3
"""Prepare the current stage outside the checkout; never include runtime secrets."""
from pathlib import Path
import shutil, sys

source = Path(__file__).resolve().parents[1]
destination = Path(sys.argv[1]).resolve()
if destination.exists():
    raise SystemExit('El destino ya existe. Usa un directorio nuevo; no se sobrescribirá.')
destination.mkdir(parents=True)
app = destination / 'jmac_app'
app.mkdir()
for name in ['app','bootstrap','lang','config','database','public','resources','routes','docs','tests','scripts','review','deployment','.github']:
    def ignore(directory, names):
        return [n for n in names if n in ['JMAC-etapa-1.html','__pycache__','hot','.DS_Store','database.sqlite','node_modules'] or n.endswith(('.pyc','.log','.sqlite')) or (Path(directory).name == 'cache' and n.endswith('.php'))]
    shutil.copytree(source / name, app / name, ignore=ignore)
for name in ['artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','README.md','AGENTS.md','phpunit.xml','.env.example','.gitignore','.gitattributes','.editorconfig','.npmrc']:
    shutil.copy2(source / name, app / name)
shutil.copy2(source/'deployment/cpanel/.env.cpanel.example', app/'.env.cpanel.example')
shutil.copy2(source/'deployment/cpanel/owner-setup.example.json', app/'owner-setup.example.json')
for rel in ['app/private','framework/cache/data','framework/sessions','framework/views','logs']:
    (app/'storage'/rel).mkdir(parents=True, exist_ok=True)
(app/'bootstrap/cache').mkdir(parents=True, exist_ok=True)
public = destination/'archivos_publicos'
shutil.copytree(app/'public', public)
shutil.copy2(source/'deployment/cpanel/index.php', public/'index.php')
review=destination/'revision_local';review.mkdir()
shutil.copy2(source/'review/JMAC-2.0-VISTA-PREVIA.html', review/'JMAC-2.0-VISTA-PREVIA.html')
shutil.copy2(source/'docs/INSTALL_CPANEL.md', destination/'INSTALAR_EN_CPANEL.md')
(destination/'LEER_PRIMERO.txt').write_text('''JMAC WEB / JMAC 2.0 — PAQUETE INTEGRAL PARA STAGING

Hitos 1–6 implementados y probados localmente. No reemplaces producción.
Contiene sitio, CMS visual, catálogo nuevo, solicitudes y operación, dependencias PHP runtime y assets compilados.

1. Lee INSTALAR_EN_CPANEL.md antes de subir.
2. jmac_app va FUERA de public_html. .env/storage/vendor siempre privados.
3. Opción A: document root staging = jmac_app/public.
4. Opción B: copia SOLO archivos_publicos al público de staging; adapta index.php.
5. Crea BD NUEVA MySQL. Configura .env desde .env.cpanel.example; permisos 0600.
6. No hay usuario/contraseña default. Crea archivo privado del dueño y ejecuta
   jmac:install-staging por Terminal o Cron según la guía. PHP CLI >=8.3 requerido.
7. Activa MFA en el primer acceso. El catálogo comienza vacío y páginas en borrador.
8. revision_local contiene HTML para abrir localmente; acciones locales; no ejecuta PHP ni envía solicitudes reales.

Ninguna configuración, datos o clave real viene dentro del ZIP.
El hosting/cPanel real, SMTP, PHP 8.3/MySQL 8 del hosting y CI están pendientes.
Firma visual/editorial y producción NO aprobadas.
''')
print(str(app))
