# JMAC web · JMAC 2.0

## Alcance y estado
Repositorio inicial vacío, rama local sin commits. Paquete leído íntegro: README, prompt, 01–09, informe consolidado, JSON, tres CSV, verificación y SQL opcional. Los dos ZIP son idénticos (SHA256 f7f33b7f30e07bad278119097e41e13c4943c85a15c53f380ff92bb66e54d1d8). Los 30 checksums del paquete coinciden. No hay respaldos WP ejecutables en el paquete. Fuentes guardadas fuera del checkout/release en /workspace/reference.

## Arquitectura
Monolito PHP >=8.3, Laravel estable, Blade SSR, MySQL 8/MariaDB >=10.11 (supuesto por verificar en cPanel), Vite para JS/CSS estáticos. Solo public/ es document root. .env, vendor y storage son privados. Sesiones/cache/cola database o file. Cron finito procesa outbox; sin Node/Redis/Supervisor persistentes. Cada dominio tiene servicios, validación, controlador y política; no HTML/PHP/JS arbitrario del editor.

## Dominios y relaciones
Auth: users ↔ user_roles ↔ roles ↔ role_permissions ↔ permissions; MFA cifrada, sesiones revocables.
CMS: pages → page_revisions inmutables; punteros independientes a borrador/publicación; site_themes versionados; nav_menus → nav_items jerárquicos; media candidato/aprobado.
Catálogo: categories jerárquicas ↔ category_product ↔ products → product_media/product_attributes; brands → products. Todo vacío.
Ventas: preorders → preorder_items snapshots y preorder_events; sequences por año, idempotency_key única; notifications_outbox insertada en la misma transacción.
Comunicación: contact_submissions; posts → post_revisions; post_categories. Todo vacío.
Sistema: site_settings, redirects, audit_logs redacted. import_batches/import_errors gestionan Excel nuevo desde Admin: preview cifrado, sumar/reemplazar, backup, transacción/idempotencia y auditoría, sin catálogo heredado. ADR_004_EXCEL.md y IMPORTACION_EXCEL.md detallan el contrato.

## Seguridad
Auth de sesión, MFA obligatoria para administradores, RBAC de cada acción en servidor, CSRF, limits, TLS, escape Blade/CSP, SQL parametrizado, media redecodificada, storage privado. Contraseñas sin valor default. Comando interactivo privado crea admin; nunca semilla de usuarios. Auditoría no contiene contraseñas ni PII completa. Consentimiento y contactos pendientes del dueño.

## Publicación
Textos semilla e imágenes son candidatos; nunca autopublicación. Borrador no altera revisión publicada. Rollback crea otra revisión y conserva historia. Staging noindex. Producción/cPanel/DNS no se modifican sin autorización.
