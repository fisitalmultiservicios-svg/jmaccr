# ADR 001 · PHP/Laravel SSR para cPanel
Estado: elección para desarrollo; compatibilidad del hosting pendiente.

Decisión: Laravel estable compatible con PHP >=8.3, Blade y Vite compilado; MySQL/MariaDB, cache/sesión database/file, cron finito. Se fijarán versiones exactas mediante composer.lock y package-lock.json. Framework reduce implementación propia de sesiones/CSRF/validación/cifrado/ORM. No usar Filament como sustituto del constructor visual contractual.

Alternativas: PHP sin framework reduce dependencias pero obliga reimplementar seguridad/ORM/migraciones; CodeIgniter 4 se reserva si límites reales impiden Laravel. SPA/API separada añade complejidad sin beneficio y se descarta. WordPress queda excluido por contrato.

Laravel 13 es la versión estable encontrada en Packagist durante setup; verificar PHP requerido en composer.json y fijar plataforma 8.3 para no introducir dependencias que requieran 8.4. Si el hosting no ofrece 8.3+, no degradar silenciosamente: documentar cambio de hosting/ADR.

Producción solo public/; vendor preconstruido si no hay Composer/SSH. SMTP/outbox por cron, sin daemons. No se afirma compatibilidad cPanel hasta prueba en hosting.
