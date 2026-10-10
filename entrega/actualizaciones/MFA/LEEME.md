# JMAC 2.0 — verificación de dos pasos configurable

Cambio solicitado por el propietario el 10 de octubre de 2026 para revisar el servidor nuevo antes de modificar el DNS público.

Por defecto la verificación MFA sigue siendo obligatoria para los roles que la requieren. Únicamente `JMAC_MFA_REQUIRED=false` la suspende; el acceso conserva correo/contraseña, estado activo, revocación de sesiones, permisos, CSRF y límites de intentos. La suspensión se aplica a todos los usuarios del panel. No borra los factores previamente registrados ni modifica tablas o contenido.

## Aplicar la actualización en este cPanel

1. Descarga `JMAC-actualizacion-MFA.zip`. En Administrador de archivos entra en `/home/jmaccr`, el nivel donde aparecen `jmac_app` y `public_html`.
2. Sube el ZIP allí y usa Extract / Extraer, con destino `/home/jmaccr`. El ZIP contiene solamente dos archivos PHP bajo `jmac_app/` y su documentación/checksums. Reemplaza `jmac_app/config/auth.php` y `jmac_app/app/Http/Middleware/AdminAccess.php`. No contiene `.env`, contraseñas, usuarios, datos, dependencias ni archivos públicos.
3. En Configuración / Settings activa Show Hidden Files / Mostrar archivos ocultos. Edita `/home/jmaccr/jmac_app/.env` y añade `JMAC_MFA_REQUIRED=false`. Si esa opción ya aparece, modifica su valor en lugar de duplicarla. Conserva APP_KEY, APP_URL y todos los demás valores.
4. Si existe `/home/jmaccr/jmac_app/bootstrap/cache/config.php`, elimina únicamente ese archivo de caché generado. No borres la carpeta ni los otros archivos. Esto permite que Laravel lea la configuración nueva; no requiere Terminal ni Cron.
5. En el navegador que tiene el archivo hosts apuntando a `198.38.90.19`, abre `https://jmaccr.com/admin`. Si solicita acceso, introduce el correo y la contraseña de tu cuenta. El paso de código MFA ya no será obligatorio. Abrir `/admin` también sale de una pantalla de registro MFA que hubiese quedado abierta.

## Volver a exigir MFA

Modifica la misma opción a `JMAC_MFA_REQUIRED=true` y repite la eliminación de `bootstrap/cache/config.php` si existe. La siguiente petición al panel exigirá registro o código, incluso para sesiones que estaban abiertas sin segundo factor. Los usuarios que ya habían registrado MFA conservarán su configuración.

## Verificación

No hay migraciones, cambios de assets ni datos iniciales nuevos. Los tests cubren acceso con y sin factor registrado, regreso al requisito MFA en sesiones abiertas, rechazo de invitados y contraseñas incorrectas, permisos, CSRF, cuentas desactivadas y revocación. Valores ambiguos o una configuración ausente mantienen el requisito MFA.

Comandos del repositorio:

```sh
source /workspace/toolchain/activate
php artisan test
vendor/bin/pint --test app/Http/Middleware/AdminAccess.php config/auth.php tests/Feature/MfaConfigurationTest.php
```

El código se prueba en una base SQLite en memoria; su aplicación y resultado en cPanel deben comprobarse después de subir el parche. La edición del archivo hosts afecta únicamente al equipo Windows del propietario.

Resultado local del cambio: 77 tests / 542 aserciones correctas; Pint correcto. PHP 8.4 local; este parche usa únicamente sintaxis compatible con PHP 8.3. No se ejecutaron migraciones en el hosting.
