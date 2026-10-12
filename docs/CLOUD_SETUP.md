# Cloud local (no cPanel)

Esta máquina Debian 13 inicialmente no tenía PHP/Composer/MySQL. Se instalaron paquetes oficiales mediante índices APT con verificación de firmas y checksums y extracción sin privilegios en /workspace/toolchain/root. Fuentes apt privadas usan HTTPS deb.debian.org/debian trixie y deb.debian.org/debian-security trixie-security. No se desactivó TLS ni firma. Herramientas y datos quedan retenidos en el snapshot cloud, fuera del checkout. No se incluyen en release cPanel.

PHP 8.4.26 y extensiones, MariaDB 11.8.6, Composer PHAR del release oficial HTTPS GitHub. Packagist resolvió versiones para platform PHP 8.3.0. api.github.com estaba bloqueado: dist URLs del lock se sustituyeron por HTTPS codeload.github.com con los mismos repositories/commits; no se alteraron versiones, referencias ni checksums esperados. Composer valida el lock y la instalación.

```sh
cd /workspace/jmaccr
source /workspace/toolchain/activate
bash scripts/cloud-refresh.sh
php artisan jmac:verify-empty
php artisan test
DB_CONNECTION=mysql DB_DATABASE=jmac_test php artisan test
cd /workspace/jmaccr/public
php -d display_errors=0 -S 127.0.0.1:8081 -t /workspace/jmaccr/public /workspace/jmaccr/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

El cwd public/ del servidor es necesario. Laravel artisan serve filtra las variables de carga de bibliotecas de este PHP portable; para cloud se usa PHP directamente. Request local a /admin/login debe devolver 200 y los campos email/password; /admin anónimo debe redirigir a login. No presentar URLs localhost al usuario.

Private .env generado con key aleatoria solo local, DB socket /workspace/toolchain/mysql.sock, usuario UNIX agent, SESSION_SECURE_COOKIE=false para HTTP local; MAIL_MAILER=array evita correo real durante QA. No usar esos ajustes HTTP en cPanel HTTPS.

Para navegador: crear BD descartable jmac_browser con la cuenta local; DB_DATABASE=jmac_browser php artisan migrate --seed; DB_DATABASE=jmac_browser php tests/Browser/fixture.php; arrancar PHP con DB_DATABASE=jmac_browser; python tests/Browser/foundation.py (Playwright y Chromium del cloud). Fixture genera contraseña aleatoria solo en /tmp privado y el runner elimina el archivo al terminar. Al terminar, detener servidor de test y DROP DATABASE jmac_browser; no dejar fixtures en staging/development. Las capturas omiten las pantallas que muestran secretos.

CI no requiere toolchain portable: instala PHP normal/Node, MySQL 8 y dependencias bloqueadas. Para una nueva máquina sin el snapshot preparado, usar requisitos normales del README o recrear herramientas con paquetes oficiales verificados; no asumir que source /workspace/toolchain/activate funciona fuera de este snapshot.
