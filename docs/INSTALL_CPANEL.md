# Instalar JMAC 2.0 en cPanel · staging

Esta guía instala desde cero en staging. Para la instalación existente del propietario, usa la actualización de [PRODUCCION_1_0.md](PRODUCCION_1_0.md): no crees otra base ni repitas el instalador. La versión oficial 1.0.0 incluye sitio PHP/MySQL, CMS visual, catálogo nuevo, Excel, solicitudes, novedades y operación. El funcionamiento externo de SMTP/DNS y QA del hosting siguen pendientes. El HTML autónomo revisa una propuesta anterior; sus acciones son locales.

## 1. Antes de subir
1. Abre tu cPanel. En MultiPHP Manager selecciona PHP 8.3 o posterior para el subdominio de pruebas. Consulta al proveedor la ruta del PHP CLI correspondiente (no siempre es `/usr/local/bin/php`).
2. Confirma PDO MySQL, mbstring, openssl, fileinfo, curl, XML/DOM/XMLReader, zip, GD con WebP e intl. Memoria recomendada 256 MB; subidas de al menos 8 MB. MySQL 8/MariaDB compatible con InnoDB/utf8mb4. El hosting exacto aún no está validado.
3. En Dominios crea un subdominio de staging independiente con HTTPS/AutoSSL. Usa Directory Privacy para restringir acceso si el proveedor lo permite. No uses el directorio contaminado de WordPress.
4. En Bases de datos MySQL crea una BD NUEVA y un usuario exclusivo; asigna privilegios para migraciones. Conserva los nombres completos con el prefijo de cPanel y la contraseña en un gestor privado. Nunca uses la BD anterior.
5. Revisa `LEER_PRIMERO.txt` del ZIP antes de extraerlo. `vendor` y `public/build` están incluidos: no necesitas Composer ni Node en el hosting.

## 2. Subir las carpetas
1. Abre Administrador de archivos y entra a `/home/TU_USUARIO/`, FUERA de `public_html`.
2. Sube el ZIP a una carpeta privada temporal y pulsa Extraer.
3. Mueve la carpeta `jmac_app` a `/home/TU_USUARIO/jmac_app`. Dentro están app, vendor, storage y los archivos privados.
4. Si cPanel permite document root configurable, apunta el dominio staging a `/home/TU_USUARIO/jmac_app/public`. En este caso NO copies la carpeta pública alternativa del ZIP.
5. Si no permite ese document root, copia SOLO EL CONTENIDO de `public_html/` del ZIP oficial al directorio público NUEVO del staging. Los paquetes anteriores llamaban a esta carpeta `archivos_publicos/`. No copies jmac_app dentro del directorio público.
6. En esa alternativa edita el `index.php` público: `$jmacAppPath` debe apuntar exactamente a `/home/TU_USUARIO/jmac_app`. Su valor por defecto funciona cuando directorio público y jmac_app son hermanos; corrígelo si el público está anidado.
7. Comprueba que `.htaccess` se copió (activa Mostrar archivos ocultos). El archivo es nuevo del proyecto, no del sitio antiguo.
8. Archivos normalmente 0644 y directorios 0755. `storage` y `bootstrap/cache` requieren escritura del usuario PHP según el proveedor; nunca uses 0777. `.env` y archivo del dueño: 0600.

## 3. Configuración privada
1. Dentro de `jmac_app`, copia `.env.cpanel.example` como `.env`; NO lo pongas en el directorio público.
2. Abre `.env` y configura APP_URL con la URL HTTPS real de staging; deja APP_ENV=staging y APP_DEBUG=false.
3. Completa DB_DATABASE, DB_USERNAME y DB_PASSWORD con los datos de la BD nueva; confirma DB_HOST con el proveedor. Si una contraseña lleva espacios o `#`, entrecomíllala según el formato dotenv.
4. Deja APP_KEY vacío solo para la primera instalación: el comando generará una clave privada aleatoria. No copies claves de otra instalación.
5. Configura SMTP con datos reales del proveedor para notificaciones y recuperación de contraseña. Puerto 465 suele usar `MAIL_SCHEME=smtps`; para STARTTLS en 587 el proveedor debe confirmar el esquema `smtp`. No inventes remitentes. Mientras SMTP no exista, no se ha comprobado entrega de email.
6. Cambia permisos de `.env` a 0600 desde Cambiar permisos.

## 4. Instalación con Terminal O Cron (sin SSH)
1. En `jmac_app/storage/app/private/` crea `owner-setup.json`, usando la estructura de `owner-setup.example.json` del paquete. Completa nombre, correo propio y contraseña NUEVA. Contraseña mínima de 12 caracteres, mayúsculas, minúsculas, números y símbolos. No pegues estos valores en chat ni en repositorio.
2. Cambia permisos de `owner-setup.json` a 0600. No lo subas al directorio público.
3. Si tienes Terminal, ejecuta UNA vez (sustituye usuario y ruta PHP por los reales):

```sh
cd /home/TU_USUARIO/jmac_app
/RUTA/PHP_8_3 artisan jmac:install-staging
```

4. Si no tienes Terminal pero sí Cron Jobs, añade temporalmente un Cron cada cinco minutos con este comando (todas las rutas deben ser reales):

```sh
cd /home/TU_USUARIO/jmac_app && /RUTA/PHP_8_3 artisan jmac:install-staging >> /home/TU_USUARIO/jmac_app/storage/logs/install-staging.log 2>&1
```

5. Espera a que ejecute. En Administrador de archivos abre `storage/logs/install-staging.log` (privado). Debe indicar instalación terminada. El instalador genera APP_KEY solo si falta, ejecuta migraciones/seeds sin borrar tablas, verifica catálogo vacío y crea un único superadmin explícito. Elimina el archivo de contraseña después de crear la cuenta. No incluye contraseñas predeterminadas.
6. Retira el Cron temporal y confirma que `owner-setup.json` desapareció. Un reintento tras completar no cambia la clave ni duplica cuentas; aun así retira el Cron.
7. Si da error, corrige la causa indicada; NO uses `migrate:fresh`, NO borres tablas ni cambies de clave si ya hay datos. Conserva el log privado y consulta TROUBLESHOOTING.
8. Si el plan NO permite PHP CLI/Terminal/Cron, esta instalación no está verificada para ese plan: pide al proveedor ejecutar el comando o habilitar CLI. No uses un instalador web público como sustituto.

## 5. Primera prueba
1. Abre la URL staging: verás el sitio con contenido pendiente y catálogo vacío. Los textos semilla no están publicados.
2. Entra a `/admin/login`, usa tu cuenta nueva y activa MFA con una aplicación de autenticación (clave introducida manualmente). No fotografíes ni compartas esa clave. Guarda los ocho códigos de recuperación que se muestran una sola vez. El propietario puede suspender temporalmente el requisito mediante `JMAC_MFA_REQUIRED=false`; consulta [ADMIN_MFA_TEMPORAL.md](ADMIN_MFA_TEMPORAL.md) para aplicar el parche y reactivarlo.
3. El panel debe mostrar cero categorías, marcas, productos y novedades; las cinco páginas deben seguir en borrador.
4. En Medios importa candidatos o carga fotos nuevas. Edita ALT y aprueba explícitamente derechos/uso. SVG/ZIP/PHP se rechazan. Los PDF se reconstruyen sin acciones activas.
5. En Páginas abre Inicio, edita bloques y revisa escritorio/tablet/móvil. Guarda, abre vista previa privada y publica solo tras aprobar contenido/medios. Las demás páginas no inventan datos.
6. Diseño: logo aprobado, colores, fuente, tamaños y pie; guardar borrador y publicar. Menús: editar/reordenar/ocultar enlaces. Conserva las cinco rutas corporativas.
7. En Configuración guarda contactos reales, WhatsApp +506..., correo del equipo y política propia aprobada. Hasta aprobar privacidad no se aceptan formularios.
8. Crea categoría, marca y producto NUEVOS de prueba. Publica SKU único/medios aprobados; busca y comprueba carrito/cantidades. Usa datos ficticios al solicitar. Verifica referencia/snapshot/seguimiento en Preventas. Retira datos QA al terminar. No uses catálogos antiguos.
9. Configura SMTP cifrado en Configuración o .env. Prueba hacia TU correo; revisa Notificaciones y confirma recepción en el buzón. Aceptación SMTP no prueba lectura; abrir WhatsApp no prueba envío.
10. Crea novedad con bloques y publica; prueba retirada/rollback. Invita usuario con otro rol, revisa MFA/permisos. No entregues contraseñas por chat.
11. Crea copia cifrada y descarga clave de recuperación SEPARADA. Guarda ambas fuera del hosting. Prueba restore solo staging según BACKUP_RESTORE.md; genera copia previa, revoca sesiones y cancela correos pendientes.
12. /.env, /vendor/autoload.php, /storage/logs/laravel.log y /.git/config deben estar denegados. Comprueba HTTPS, cookies Secure/HttpOnly y staging noindex. ZIP/revision_local nunca públicos.

## 6. Cron de operación
En Cron Jobs crea tarea cada cinco minutos, sustituyendo rutas reales:

    cd /home/TU_USUARIO/jmac_app && /RUTA/PHP_8_3 artisan jmac:operations --limit=20 >> /home/TU_USUARIO/jmac_app/storage/logs/operations.log 2>&1

Sin queue:work, Supervisor ni Node. Procesa hasta 20 correos, 10 publicaciones por tipo y copia diaria solo si se activa en Diagnóstico. Lock evita solapamiento. Revisa heartbeat y log privado. Errores SMTP quedan en outbox; no borran solicitudes.

## 7. Validar antes de producción
Completa QA_MATRIX en el hosting: PHP web/CLI 8.3+, versión MySQL/MariaDB, SSL, document root, SMTP, Cron, restore, legal/contacto e imágenes. Local: PHP 8.4/MariaDB 11.8; PHP 8.3/MySQL 8 del hosting y CI pendientes. T048 pendiente; sin réplica exacta aprobada. Producción/DNS solo con autorización explícita y plan UPDATE_ROLLBACK.md.

## 8. Qué puedes subir a GitHub
El código del repo puede subir sin `.env`, contraseñas, logs ni bases. El ZIP con vendor se entrega para cPanel y no necesita versionarse como código. `revision_local/JMAC-2.0-VISTA-PREVIA.html` se abre localmente: no tiene seguridad/backend PHP y no debe reemplazar la app.
