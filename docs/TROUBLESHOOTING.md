# Problemas de la etapa 1

- **HTTP 500:** confirmar PHP 8.3+, extensiones, ruta privada de index.php, vendor y permisos de storage/bootstrap/cache. Mantener APP_DEBUG=false públicamente. Leer logs privados, sin compartir contraseñas/claves ni PII.
- **CLI distinto al PHP web:** proveedor debe dar ruta PHP 8.3 CLI; cambiar MultiPHP web no siempre cambia CLI.
- **Instalador solo staging HTTPS:** .env exige APP_ENV=staging, APP_URL=https://dominio-real y APP_DEBUG=false. Esto no confirma que el SSL del dominio funcione: comprobarlo en navegador.
- **No conecta BD:** usar nombres completos con prefijo de cPanel, privilegios en BD nueva y host real. No copiar DB_SOCKET del cloud.
- **Archivo del dueño rechazado:** nombre exacto owner-setup.json en storage/app/private; JSON válido, permisos 0600, sin symlink; nombre/email/contraseña fuerte requeridos.
- **La base contiene datos:** el instalador protege esos datos. Usar BD nueva exclusiva; no borrar ni importar históricos para evadir la protección.
- **Ya completada:** no repetir key:generate ni crear otra cuenta. Retirar Cron temporal, confirmar eliminación del archivo privado. Activar MFA con la cuenta creada.
- **419:** recargar login; confirmar APP_URL, HTTPS, SESSION_SECURE_COOKIE=true, dominio cookie y escritura de sesión. No deshabilitar CSRF.
- **404 excepto inicio:** comprobar .htaccess/rewrite/document root. No mezclar archivos WP.
- **CSS ausente:** copiar public/build o archivos_publicos/build completos; manifest está en jmac_app/public/build. En alternativa de public_html, las dos copias deben corresponder al mismo release.
- **Reset no entrega email:** configurar SMTP real/TLS/remitente y revisar acceso saliente. Reset con token fue probado localmente; SMTP del proveedor no fue validado. No implica que WhatsApp o correo se entregaron.
- **MFA código inválido:** sincronizar reloj del móvil/servidor; no reutilizar un código ya consumido; usar recovery de un solo uso si corresponde. No hay puerta trasera/default password.

Sin PHP CLI ni Cron, solicitar apoyo del proveedor para ejecutar el comando. No existe un instalador web público. No se ha probado el despliegue en el cPanel real ni autorización de producción.
