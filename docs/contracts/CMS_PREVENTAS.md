# Contratos de implementación

## Rutas previstas
Públicas GET /, /nosotros/, /contacto/, /novedades/, /catalogo/, /catalogo/{slug}/, /novedades/{slug}/, /politica-de-privacidad/, /robots.txt, /sitemap.xml.
GET /api/catalog/search: q, category, brand, attributes, page; 12–24 resultados, solo publicados, sin precios.
POST /api/preorders y /api/contacts: sesión/CSRF, honeypot, rate limit, validación servidor.
Admin: /admin/login, MFA enroll/challenge, /admin (Auth + MFA); /admin/pages, theme, menus, media, catalog/categories/brands/products, preorders, contacts, posts, users, SEO, settings, audit, backups, diagnostics. Cada mutación exige permiso/CSRF y audit; preview privado noindex.

## Permisos
superadmin: todos, MFA obligatoria. administrador: contenido/diseño/catálogo/ventas, MFA; sin secretos/restore/usuarios por defecto. editor_web: pages, posts, menus, media, theme limitado; sin PII. gestor_catalogo: categorías/marcas/productos/atributos/media. ventas: preorders y contacts, asignación/estados/export; sin diseño/productos. consulta: permisos view expresamente asignados, sin PII por defecto. Ningún rol se infiere de UI.

## Bloques
Envelope schema_version, blocks[]: id estable, type allowlist, data tipada, style tokens/valores limitados, visibility desktop/tablet/mobile. Tipos mínimos hero, slider, heading, rich_text, text_image, image, gallery, columns, icon_features, cta, buttons, testimonials aprobados, contact_form, whatsapp, news_feed, catalog_categories, featured_products, spacer, divider; faq/map opcionales. Media por IDs aprobados al publicar; URLs internas o https seguras; nada de scripts/iframe libre. Limitar profundidad/tamaño. Revisiones inmutables, publicación independiente del draft y rollback nuevo revision. Preview autenticado o token breve noindex.

## Preventa
UUID idempotency_key ligado a sesión/hash de payload; customer.name y phone obligatorios, company/email opcionales; items product_id y quantity entero 1..límite; notes opcionales, privacy_accepted y versión legal. Servidor valida productos publicados y toma SKU/nombre/unidad/atributos. Transacción: token existente retorna referencia previa sin divulgar PII; lock secuencia del año, unique(year,seq), insertar solicitud, líneas snapshot, evento recibida y outbox unique. Commit antes de confirmación 201; retry 200 misma referencia. Reuso token con payload diferente 409. Bloqueos y colisiones se reintentan de forma acotada. SMTP caído no revierte solicitud. WhatsApp click-to-chat no prueba envío.
Estados recibida → en_revision → cotizada → gestionada → cerrada, cancelada desde estados permitidos. Cambio con usuario/nota/timestamp y privilegio para excepciones.

## Datos iniciales

Actualización autorizada del 13 de octubre de 2026: privacidad deja de ser condición de consulta/preventa. privacy_accepted/consent_version no se exigen al cliente; la columna consent_version de las nuevas solicitudes guarda sin_aceptacion_requerida sin simular aceptación. Las versiones/snapshots anteriores permanecen; reintentos de solicitudes anteriores se comparan con la misma sesión y datos protegidos. El documento legal es independiente y editable, sin publicación automática. Cantidad se elige antes de añadir tanto en tarjetas como fichas, respeta el máximo configurable y se valida en servidor. Esta instrucción del propietario sustituye la aceptación obligatoria de la definición inicial anterior; permisos, protección de PII, transacciones/consecutivos, antispam y CSRF se conservan.
0 categories, brands, products, product_media, preorders, preorder_items, contacts, posts, customers y usuarios heredados. Seed solo roles/permisos/settings técnicos, páginas DRAFT y media candidato. Admin nuevo explícito por comando seguro. CSV vacío no es un importador.
