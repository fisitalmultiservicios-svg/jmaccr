# Compatibilidad cPanel y cuestionario
No se dispone de acceso a cPanel. Usuario confirma PHP 8.3 y MySQL con phpMyAdmin. Versión MySQL y demás capacidades no verificadas.

| Capacidad | Requisito provisional | Estado / solución |
|---|---|---|
| PHP web y CLI | >=8.3, versión con soporte de seguridad | Consultar MultiPHP Manager y proveedor |
| Extensiones | PDO MySQL, mbstring, openssl, fileinfo, curl, xml, zip, intl, GD | Solicitar activación si falta |
| BD | MySQL 8 o MariaDB >=10.11, utf8mb4, InnoDB/FK | Consultar phpMyAdmin; crear BD nueva |
| Document root | Solo /home/USUARIO/jmac_app/public | Consultar Dominios; alternativa índice público con bootstrap privado |
| HTTPS | SSL válido en staging y producción | Consultar SSL/TLS Status |
| Cron | PHP CLI, ejecución finita cada 1–5 min | Consultar Cron Jobs y límites |
| SSH/Composer | Opcional | Si no existe, subir vendor/build hechos en CI |
| SMTP | TLS y puerto saliente permitido | Proveedor debe confirmar; no pedir claves por chat |
| Recursos | Memoria PHP >=128 MB, disco para release y backups | Consultar límites y cuota |
| Apache | Rewrite, sin listado de directorios | Verificar en staging |

Para el dueño: indica proveedor/plan; abre MultiPHP Manager y anota versiones; abre phpMyAdmin y anota versión del servidor; confirma si ves Terminal, Cron Jobs y Dominios; pregunta si se permite document root fuera de public_html y SMTP TLS. No compartas contraseñas ni capturas con secretos. Sin estos datos avanzamos localmente; despliegue real queda pendiente.

Máquina cloud: Debian 13, inicialmente sin PHP/Composer/MySQL. Toolchain aislado en /workspace/toolchain, sin modificar sistema ni hosting. Se registrarán versiones y evidencia al ejecutar pruebas.
