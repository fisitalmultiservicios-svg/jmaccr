# Plan de implementación y puertas de avance

| Hito | Trabajo y pruebas | Estado |
|---|---|---|
| 0 | Fuentes completas, repo, ADR, contratos, riesgos y cuestionario cPanel | Documentado; hosting pendiente |
| 1 | Laravel, CI, migraciones, semillas borrador, Auth/RBAC/MFA, CSRF/limits, media segura, audit; tests cero y seguridad | Probado local; compatibilidad hosting pendiente |
| 2 | Cinco páginas SSR, detalles, tokens, navegación/footer, responsive, SEO y vacíos; HTTP/browser | Probado H2, navegador 25 combinaciones |
| 3 | Biblioteca completa de bloques, drag/inline, breakpoints, theme/menu/media, preview, revisiones/publish/rollback; tests de aislamiento | Probado H3: constructor/revisiones/media |
| 4 | CRUD jerarquías/marcas/productos/atributos/media, búsqueda accent-insensitive/filtros, carrito; fixtures nuevos y limpieza | Probado H4: catálogo nuevo y carrito |
| 5 | Preventas transaccionales/idempotentes/concurrencia, snapshots/timeline, ventas/contactos, outbox SMTP y WhatsApp; fallos de correo | Probado H5: concurrency/SMTP local |
| 6 | Blog/revisiones, SEO/redirects, audit, backups/restore/diagnóstico, permisos, carga 1k productos | Probado H6: novedades/roles/backup/restore |
| 7 | 48 casos T001–T048, capturas/responsive/a11y, release checksums, manuales cPanel, staging real y aprobación | Artefactos/revisión local preparados; QA hosting/T048 pendientes |

Cada hito exige reporte de archivos/migraciones/rutas, comandos y resultados, evidencia y pendientes. No afirmar cierre con mocks. Datos ficticios solo tests transaccionales o BD staging descartable. Nunca tocar producción automáticamente.

## Versión oficial 1.0.0 solicitada después de la instalación

Diseño reparado aprobado por el propietario. Retirada del banner de cuatro páginas e importación Excel nueva con vista previa, Sumar/Reemplazar, copia cifrada, transacción y permisos: implementadas y probadas. Sin migraciones ni datos heredados. Actualización incremental preserva configuración/datos; paquete completo para instalaciones nuevas. STATUS.md registra las pruebas y manuales; aplicación remota, SMTP/DNS y aceptación externa siguen pendientes.

## Riesgos
| Riesgo | Nivel | Mitigación y puerta |
|---|---|---|
| cPanel desconocido | Alto | Cuestionario, requisitos PHP/DB y prueba staging antes de release |
| hosting anterior comprometido | Alto | Cuenta nueva/limpieza certificada; no reutilizar código ni secretos |
| textos/contactos/derechos pendientes | Alto | Borradores/candidatos, bloqueo de publicación de media sin aprobación |
| CMS incompleto o controles ficticios | Alto | H3 con pruebas de persistencia, publicación y rollback por control |
| duplicados/concurrencia preventa | Alto | Unique, transacciones, locks y tests MySQL concurrentes |
| fuga PII | Alto | RBAC, cifrado/retención, export privado, logs redacted |
| SMTP/cron limitado | Medio | Outbox durable, cron finito, estados reales y prueba con fallo |
| fidelidad visual no verificable | Medio | Reconstrucción propuesta, capturas y aprobación del dueño |
| dependencias/red | Medio | TLS/firma/checksum, locks, auditoría; no ignorar verificación |
