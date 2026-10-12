# Hito 3 · CMS visual y medios

Migración `2026_10_10_000001_extend_cms`: metadatos en revisiones de página, programación, secciones reutilizables, revisiones de menús, variantes de media. Dominio Cms/Blocks y Pages; controladores Admin/Page, Theme, Menu; layout administrativo, constructor JS, biblioteca de 21 bloques, estilos responsive; media candidata, aprobación explícita, referencias antes de eliminar, JPEG/PNG/WebP/AVIF soportado por GD y PDF reconstruido con FPDI/FPDF.

Rutas `/admin/pages/{id}/edit`, save/preview/publish/rollback/archive, `/admin/sections`, `/admin/theme` y acciones, `/admin/menus` y acciones, edición/aprobación/eliminación de media y carga privada de candidatos corporativos. Nuevas páginas publicadas usan rutas propias. RBAC se carga una vez por solicitud y se vuelve a consultar en la siguiente: no se conservan permisos antiguos entre solicitudes.

`php artisan test --compact`: 36 pruebas, 247 assertions aprobadas SQLite. Pint, build y Composer audit aprobados. Browser cms.py: login/MFA, carga/aprobación de candidato, edición, save que no altera público, publicación, add/undo/redo y 5 anchos. Capturas h3-constructor-desktop.png y h3-publicado-desktop.png; evidencia h3-browser.json. Fixture en BD descartable, sin alterar páginas/productos del desarrollo.

Para probar: crea administrador por instalación privada, carga candidatos, aprueba derechos de las imágenes elegidas, abre una página, selecciona bloque, cambia texto/imagen, guarda, compara público, publica y restaura una revisión en nuevo borrador. Menús y diseño global tienen revisión/publicación propia. Programación requiere el Cron de operaciones que se incorpora en H6. Aprobación visual y hosting real continúan pendientes; no se promete réplica exacta.
