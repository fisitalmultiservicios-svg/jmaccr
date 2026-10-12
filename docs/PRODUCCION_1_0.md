# JMAC 2.0 — versión oficial 1.0.0

Preparada el 12 de octubre de 2026, por solicitud del propietario. Es la versión del software para instalar; su publicación DNS, SMTP y funcionamiento real en el hosting deben verificarse por separado. El propietario confirmó el diseño después de la reparación de permisos y solicitó quitar el banner común de las cuatro páginas secundarias e incorporar Excel.

## Para la instalación que ya tienes en /home/jmaccr

Usa **JMAC-2.0-PRODUCCION-1.0.0-ACTUALIZACION.zip**. No reinstala, no contiene .env, usuarios, base de datos, storage, semillas ejecutadas ni claves. Incluye PHP, vistas, rutas, la dependencia Excel/autoload, CSS y logo compilados con permisos 0644. Carpetas públicas 0755; no altera los permisos de la raíz privada jmac_app. No contiene JavaScript: el script `app-B9eZnYus.js` ya instalado conserva exactamente el mismo hash. No hay migraciones nuevas.

1. En JMAC → **Sistema → Backups**, crea y descarga una copia antes de actualizar. Mantén privada la copia y su clave de recuperación. Copia también los archivos de aplicación actuales fuera de public_html para poder revertir código si fuera necesario.
2. Descarga el ZIP de actualización y súbelo con cPanel a **/home/jmaccr**, donde están jmac_app y public_html. Espera la aceptación del antivirus; si se rechaza, conserva el aviso y detén la instalación.
3. Selecciona el ZIP → **Extraer / Extract**, destino **/home/jmaccr**. Actualiza los archivos incluidos. El índice público ahora registra public_html como ruta pública real de Laravel; .env/storage/vendor siguen fuera del document root.
4. En JMAC → **Sistema → Diagnóstico → Vaciar caché**. El botón limpia vistas, rutas y configuración compiladas para cargar esta versión. Recarga con Ctrl+F5. Diagnóstico debe mostrar **Versión oficial 1.0.0**.
5. En Administrador de archivos abre **/home/jmaccr/jmac_app/.env**, pulsa Editar y comprueba las líneas `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://jmaccr.com`. Conserva APP_KEY, contraseña de MySQL, usuarios y la decisión vigente sobre MFA. Después pulsa nuevamente Vaciar caché. Los archivos ocultos se muestran desde Settings → Show Hidden Files.
6. En **Catálogo → Productos y especificaciones → Cargar productos desde Excel**, descarga la plantilla y usa Sumar o Reemplazar con vista previa. Guía completa en IMPORTACION_EXCEL.md.

El banner con título/Ver catálogo/Contáctanos desaparece de Nosotros, Contáctanos, Novedades y Catálogo. El de Inicio se conserva. Los títulos accesibles/SEO permanecen; no se borran bloques editados ni se publican contenidos corporativos pendientes.

## Entrega integral para una instalación nueva

**JMAC-2.0-PRODUCCION-1.0.0-COMPLETO.zip** contiene aplicación limpia, dependencias PHP runtime, recursos estáticos compilados, fuentes y manuales. Es un paquete completo: no se debe extraer sobre una instalación con datos y .env propios. Primero se monta jmac_app fuera del document root y únicamente public_html se coloca en el público. La configuración inicial privada y dueño se provisionan con el procedimiento de INSTALAR_EN_CPANEL.md, inicialmente staging; luego se cambia a production. La instalación sigue iniciando con cero productos, categorías, marcas, preventas y artículos.

El paquete completo incluye JavaScript normal de la app y recursos del framework. El hosting conocido bloqueó un ZIP con JavaScript; no se certifica que acepte el integral. En la instalación existente se utiliza el parche sin JS. Ninguna entrega desactiva el antivirus, oculta extensiones ni usa descargas PHP para eludirlo.

## Comprobar antes de abrir el dominio al público

El archivo hosts de Windows permite seguir revisando el servidor nuevo sin cambiar DNS. PHP web/CLI debe ser 8.3+, con MySQL/MariaDB y las extensiones indicadas en Diagnóstico, especialmente DOM/XMLReader/ZIP para Excel. SSL debe ser válido para jmaccr.com.

Completa datos de contacto, privacidad y contenidos pendientes desde el panel; las fotos siguen candidatas hasta aprobación. Configura SMTP y realiza la Prueba SMTP del panel, comprobando recepción real. Para procesar notificaciones pendientes, programa el Cron finito que ya indica el manual: `cd /home/jmaccr/jmac_app && /usr/local/bin/php artisan jmac:operations --limit=20`, cada cinco minutos. No requiere procesos persistentes. La configuración de MFA temporal del dueño se conserva, aunque una instalación nueva lo exige por defecto.

La aprobación gráfica informada no sustituye QA de hosting ni verificación de correo/DNS. No se afirma una réplica visual exacta ni 48/48 casos externos aprobados. El cambio de DNS se realiza solo cuando el propietario lo decide; esta entrega no modifica DNS ni cPanel automáticamente.

## Reversión

La actualización no cambia el esquema ni datos comerciales. Para revertir código, restaura los PHP/vistas/rutas/autoload y manifiestos previos desde tu copia de archivos y vacía caché. Conserva .env/APP_KEY/storage y las bases. La restauración de una copia completa con datos continúa restringida a staging; no la ejecutes en producción para deshacer solo estilos.
