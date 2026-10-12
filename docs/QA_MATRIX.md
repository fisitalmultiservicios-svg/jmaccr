# QA contractual · evidencia y pendientes

Candidato para staging, no autorización de producción. No se declara 48/48 aprobado. Los casos locales no certifican el hosting real.

| ID | Módulo | Escenario | Resultado requerido | Estado / evidencia |
|---|---|---|---|---|
| T001 | Instalación | BD `migrate:fresh --seed` | categorías=0, marcas=0, productos=0, noticias=0, preventas=0 | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T002 | Sitio | GET `/`, `/nosotros/`, `/contacto/`, `/novedades/`, `/catalogo/` | 200, diseño corporativo, sin WordPress/SEO basura | PASSED PublicSite/Catalog tests y navegador h2/h4 |
| T003 | SEO | Sitemap/robots/canonical | 0 rutas basura, índice solo páginas publicadas | PASSED final-contract-performance: sitemap solo publicado y robots público simulado; robots estático retirado, hosting pendiente |
| T004 | Diseño | Cambiar color/hero en admin y publicar | Front actualiza sin programar; borrador no altera público | PASSED CmsTest; h3-browser y revisión local |
| T005 | Diseño | Restaurar revisión previa | Visual y contenido regresan, auditoría mantiene evento | PASSED CmsTest; h3-browser y revisión local |
| T006 | Menú | Añadir/reordenar/ocultar enlace | Reflejado desktop/móvil, URL válida | PASSED CmsTest; h3-browser y revisión local |
| T007 | Admin | Visita `/admin` sin login | 302 login o 401, nunca información sensible | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T008 | Admin | Login incorrecto repetido | Rate limit, sin enumeración de usuario | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T009 | Permisos | Rol ventas intenta cambiar tema/producto | HTTP 403, no alteración BD | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T010 | Permisos | Rol editor intenta ver datos de preventas | HTTP 403 | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T011 | MFA | Superadmin sin segundo factor configurado | Flujo de enrolamiento seguro obligatorio | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T012 | Categorías | Crear categoría con imagen | Visible en catálogo en orden definido | PASSED final-contract-performance: categoría nueva con imagen aprobada renderizada |
| T013 | Categorías | Duplicar slug o ciclo | Error validación, BD consistente | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T014 | Productos | Crear borrador | No aparece públicamente | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T015 | Productos | Publicar nuevo producto con SKU | Búsqueda nombre/SKU, ficha y galería funcional | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T016 | Productos | SKU duplicado | Error validación sin sobrescribir | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T017 | Productos | Archivar producto | Desaparece de nuevos carritos, preventas antiguas mantienen snapshot | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T018 | Catálogo | `/catalogo/` vacío | 200, estado vacío elegante, cero datos ficticios | PASSED PublicSite/Catalog tests y navegador h2/h4 |
| T019 | Búsqueda | Palabras con acentos, SKU, sin resultados | Respuesta correcta y paginada | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T020 | Filtros | Enlace `?jmac_cat=cadenas` con categoría inexistente | Vista vacía válida, sin error 500 | PASSED PublicSite/Catalog tests y navegador h2/h4 |
| T021 | Carrito | Agregar/quitar/modificar cantidad | Contador y resumen sincronizados | PASSED h4-browser y final-ux-browser |
| T022 | Preventa | Enviar datos incompletos | 422 o errores de campo, nada guardado | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T023 | Preventa | Producto despublicado en carrito | Rechazo claro, no se inserta ítem inválido | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T024 | Preventa | Enviar válida | Transacción confirmada, número único y snapshot | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T025 | Preventa | Doble click/reintento misma idempotency_key | UNA sola solicitud, misma referencia | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T026 | Preventa | Dos solicitudes simultáneas diferentes | Consecutivos únicos y consistentes | PASSED h5-concurrency-smtp: 4 workers, 10 intentos, 7 consecutivos únicos |
| T027 | Preventa | SMTP caído | Solicitud guardada, outbox failed/pending visible, no falsa entrega | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T028 | Preventa | Cambio de estado por ventas | Timeline y usuario registrado, notificación apropiada | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T029 | Contacto | Formulario válido con correo caído | Contacto persiste en BD, error notificación rastreable | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T030 | Contacto | spam/honeypot/rate limit | Bloqueo/rate limit sin afectar tráfico legítimo | PASSED SalesTest y h5-browser; fallos SMTP y consentimiento |
| T031 | Blog | Publicar/retirar novedad | Index y detalle correctos, SEO actualizado | PASSED SystemTest/h6-browser/final-contract-performance: publicar, índice, detalle y retirada 404 |
| T032 | Media | Subir JPG/PNG/WEBP válidos | Miniaturas + ALT + reencode | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T033 | Media | Intentar PHP disfrazado `.jpg`/ZIP/SVG activo | Rechazo, nunca ejecución | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T034 | XSS | Inyectar JS en editor/ficha/contacto | Escapado/sanitizado, sin ejecución | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T035 | CSRF | POST modificación sin token | Rechazo, BD sin cambios | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T036 | SQLi | `q` con caracteres especiales SQL | Resultado seguro, sin errores SQL ni fuga | PASSED CatalogTest/Cms/Sales y h4-browser; fixtures nuevos |
| T037 | Rendimiento | 1k productos simulados en test | Paginación sin cargar todo, tiempos medidos | PASSED CatalogTest: 1.000 nuevos fixtures, 12 por página, <5s local; HTTP cinco muestras promedio 429.56ms, sin Lighthouse del hosting |
| T038 | Responsive | 360/390/768/1024/1440 px | Sin scroll horizontal anómalo, CTA accesible | PASSED h2/h3/h4/final-ux navegador: 360/390/768/1024/1440, sin overflow |
| T039 | Accesibilidad | Teclado/lector texto/labels/foco | Cumple controles básicos WCAG AA | PARCIAL: etiquetas, foco/skip/nav semántica; lector de pantalla y auditoría WCAG AA completa pendientes |
| T040 | cPanel | Instalar artefacto limpio | Web y admin funcionan sin servidor Node | PARCIAL: instalación del paquete runtime local probada; cPanel del dueño sin acceso |
| T041 | cPanel | Visitar `/.env`, `/vendor/`, `/storage/logs/...` | Denegación consistente, sin filtración | PASSED local rutas privadas; revalidar Apache/cPanel real |
| T042 | cPanel | Cron/colas/SMTP | Procesan, registran éxito/error, no requieren daemon | PASSED Cron finito y SMTP controlado local; SMTP/cron/SSL del hosting pendientes |
| T043 | Operación | Restaurar backup en staging | App recupera páginas, productos nuevos y usuarios autorizados | PASSED SystemTest: BD, página/producto nuevos, rol usuario y medios restaurados; h6-browser restore/logout |
| T044 | Auditoría | Cambiar roles/diseño/preventa | Registro de usuario/fecha/cambio, sin secretos | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T045 | Migración | Buscar referencias antiguas en BD nueva | No existe ninguna fila heredada, ni productos históricos | PASSED Foundation/Cms/Sales/System tests en SQLite y MariaDB |
| T046 | Publicación | Referencias de assets | Solo `assets_limpios/` aprovados y nuevos uploads sanos | PASSED bloqueo de candidatos sin aprobación; fotos originales todavía candidatas, dueño pendiente |
| T047 | Seguridad | Inspección dependencia/hardcoded keys | No hay secretos/WordPress/malware en repo o release | PASSED composer/npm audit sin avisos, claves/runtime excluidos y manifiesto ZIP; no auditoría externa completa |
| T048 | Aprobación | Validación visual con dueño | Capturas y checklist aprobados, diferencias conocidas listadas | PENDIENTE: aprobación visual/editorial del dueño, no réplica exacta verificada |

64 tests/431 aserciones PASSED por motor SQLite y MariaDB. Reportes final-tests-*.json. Lecturas/CRUD/snapshots/RBAC/XSS/CSRF/recovery/restore probados. Pruebas navegador con fotos y datos QA en bases descartables eliminadas. CI remota PHP8.3/MySQL8, Lighthouse y T048 pendientes.
