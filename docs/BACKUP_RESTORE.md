# Copias y recuperación
1. Panel > Backups > Crear copia cifrada. Espera éxito; permanece privada, nunca en public_html.
2. Descarga .jmb. Da tu contraseña actual en Descargar clave privada. Guarda la .key SEPARADA de la copia, fuera del hosting y protegida. Sin clave no hay recuperación; no la envíes por chat.
3. Conserva ZIP del código/checksum. Copia de datos no contiene código, .env completo ni credenciales DB. APP_KEY se incluye dentro del archivo cifrado para recuperar PII/SMTP/MFA.
4. Diagnóstico permite copia diaria y conservar 2–30 copias; requiere Cron. Desactivado inicialmente.
5. Solo STAGING: seleccionar copia, escribir RESTAURAR STAGING, confirmar y dar contraseña. Verifica autenticidad AES-GCM, tamaños/rutas/esquema/filas/checksums antes de modificar. Genera copia previa del estado actual (registra medios ya faltantes), restaura tablas/medios y revoca sesiones. Cancela mensajes pendientes para evitar reenvíos; revisa outbox antes de generar otros.
6. Inicia sesión, verifica MFA, páginas/productos nuevos/usuarios/medios y solicitud QA nueva. Datos QA no incluidos en release.
7. Hosting nuevo: instala MISMA versión y copia clave original a jmac_app/storage/app/private/backups/recovery.key (0600) antes de importar .jmb. Importar no restaura. Conserva DB/URL staging; restore actualiza solo APP_KEY si difiere y limpia cache. Permiso .env 0600; verifica en petición nueva.
8. CLI/Cron privado: php artisan jmac:backup. Restore: php artisan jmac:backup --restore=NOMBRE.jmb --confirm-staging. Sustituye ruta PHP CLI real. Sin instalador/restore público.
9. Restore bloqueado en production. Recupera/valida staging y solicita autorización para producción. Límite 240 MB / 50.000 filas por tabla; usa backup del hosting para mayor volumen. Recomendada memoria >=256 MB y CLI en copias grandes. Si falta un medio al crear copia normal, no anuncia copia completa.
10. Fallo: conserva mensaje/copia previa; no generes otra APP_KEY. Mantén staging protegido. Cambiar clave haría ilegibles PII/MFA/SMTP.
