# Actualizar / rollback
1. Producción solo con autorización. Staging separado/protegido.
2. Conservar ZIP/checksums, copia cifrada/clave separada y backup del hosting con BD/medios/.env/código. Probar restore staging.
3. Preparar carpeta nueva privada y nuevo público. vendor/build incluidos. Conservar APP_KEY y configuración privada. Solo medios de app limpia, nunca WordPress.
4. Probar migraciones en copia staging, diagnóstico, permisos/MFA, sitio/catálogo/solicitudes/SMTP/Cron y QA. No migrate:fresh sobre BD comercial.
5. Tras aprobación: ventana, congelar escrituras, mantenimiento y copia final; promover código/document root, migrate --force, limpiar cache/vistas y verificar antes de reabrir.
6. Si falla, preservar solicitudes posteriores, detener escrituras y evaluar compatibilidad del esquema antes de volver al código previo. No rollback de migraciones a ciegas. Restaurar BD sin preservar pedidos nuevos perdería datos.
7. Recuperar/validar staging primero. Producción/DNS/cPanel requieren autorización. Restore está bloqueado en production.
