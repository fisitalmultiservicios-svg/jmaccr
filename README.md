# JMAC web · JMAC 2.0

Versión oficial del software **1.0.0**, preparada el 12 de octubre de 2026. App limpia Laravel/PHP >=8.3, MySQL-MariaDB y Blade server-side para cPanel. Sitio responsive, CMS visual (21 bloques/tema/menús/medios/previews/revisiones), catálogo nuevo/carrito, importación Excel con vista previa y modos Sumar/Reemplazar, preventas/contactos transaccionales/outbox SMTP, novedades/SEO, usuarios/RBAC/MFA/auditoría/backups cifrados/restore staging/diagnóstico/Cron finito.

El propietario aprobó el diseño reparado y solicitó esta versión. [Actualización e instalación oficial](docs/PRODUCCION_1_0.md), [productos desde Excel](docs/IMPORTACION_EXCEL.md) y [estado/evidencias](docs/STATUS.md). La actualización conserva la instalación existente y no necesita nuevas migraciones. La publicación DNS, SMTP y QA del hosting no se verificaron remotamente.

Inventario con **Nombre, Descripción, Categoría, Imagen, SKU** y fotos incrustadas: [actualización y límites PHP](docs/EXCEL_CON_FOTOGRAFIAS.md).

Aviso «Excel demasiado grande o con contenido activo» con PHP en 512M: [corrección de compatibilidad y diagnóstico preciso](docs/EXCEL_ERROR_DETALLADO.md).

Error 409 durante la preparación de fotografías: [corrección y cómo retomar la importación](docs/EXCEL_PREPARACION_409.md).

Dominio oficial: [corrección de HTTP 400 y redirección a HTTPS](docs/HTTP_400_HTTPS.md).

Solicitud del propietario: [formularios sin aceptación obligatoria de privacidad y cantidad antes del carrito](docs/PRIVACIDAD_Y_CANTIDAD.md).

Corrección posterior de la página publicada: [quitar el espacio vacío del catálogo](docs/AJUSTE_CATALOGO.md). El parche se aplica después del paquete oficial 1.0.0; el código fuente de esta rama ya incorpora el ajuste.

Sin WordPress/importación vieja/pagos. Cero productos/categorías/marcas/artículos/solicitudes/usuarios iniciales, sin contraseña default. Semilla DRAFT/fotos candidatas y contactos/legal pendientes. No modifica producción automáticamente.
## Ver antes de subir
review/JMAC-2.0-VISTA-PREVIA.html: archivo único, navegador moderno, sin CDN/backend. Acciones locales/datos ficticios; panel/estados vacíos. PHP/MySQL del ZIP aporta seguridad/persistencia/SMTP reales.
## cPanel
Para una instalación nueva: docs/INSTALL_CPANEL.md, subir fuera de public_html; solo public/ o el contenido de public_html del ZIP al document root. vendor/build incluidos; sin Composer/Node en hosting. .env/storage privados, BD nueva y cuenta explícita mediante jmac:install-staging via Terminal o Cron. PHP web Y CLI >=8.3/extensiones indicadas. Conservar APP_KEY privada. Una instalación existente se actualiza según PRODUCCION_1_0.md sin repetir estos pasos.
## Desarrollo / pruebas
Cloud: source /workspace/toolchain/activate; bash scripts/cloud-services.sh.
Composer oficial: php /workspace/toolchain/composer.phar install. npm ci && npm run build. .env privado; php artisan migrate --seed. Cuenta explícita: php artisan jmac:admin CORREO_PROPIO; contraseña solicitada en privado.
Tests solo SQLite memory o BD descartable *_test: php artisan test; DB_CONNECTION=mysql DB_DATABASE=jmac_test php artisan test. vendor/bin/pint --test, composer audit --locked, npm audit y tests/Browser. No tests/migrate:fresh sobre datos comerciales. CI PHP8.3/8.4+MySQL8 configurada, no ejecutada remotamente.
Guías: docs/ADMIN_MANUAL.md, BACKUP_RESTORE.md, UPDATE_ROLLBACK.md, STATUS.md, QA_MATRIX.md, adr/001-stack.md. Fuentes leídas/verificadas fuera del release; sin respaldos contaminados.
