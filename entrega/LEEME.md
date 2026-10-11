# JMAC 2.0 — Entrega para revisión

## Descargar y ver la web

1. Descarga [JMAC-preview.zip](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-preview.zip). Si GitHub abre una pantalla del archivo, pulsa **Download raw file** (icono de descarga).
2. Extrae el ZIP.
3. Abre **JMAC-2.0-VISTA-PREVIA.html** en Chrome, Edge o Firefox.

La vista previa contiene imágenes y recursos integrados. Las operaciones del catálogo, constructor y solicitudes se guardan únicamente en tu navegador; no envía correos ni solicitudes a JMAC ni conecta con MySQL. Empieza con el catálogo vacío. Usa datos ficticios.

## Aplicación PHP para cPanel

Descarga [JMAC-cpanel.zip](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-cpanel.zip). Lee los manuales incluidos antes de instalar y prueba primero en staging. Requiere PHP 8.3+ y MySQL/MariaDB. Los recursos estáticos ya están compilados y las dependencias PHP están incluidas.

El paquete excluye claves reales, .env, usuarios iniciales, datos comerciales y respaldos antiguos. La semilla corporativa permanece en borrador. Las fotos y los contenidos requieren aprobación del propietario.

## Estado de revisión

La aplicación fue probada localmente con SQLite y MariaDB (64 tests y 431 assertions en cada ejecución). La validación en el hosting real, SMTP/cron, revisión de accesibilidad y aprobación visual/editorial siguen pendientes. La publicación en producción requiere autorización expresa del propietario.

Consulta **SHA256SUMS.txt** para comprobar la integridad de los ZIP. Esta rama conserva el paquete contractual original que ya estaba en main y añade únicamente la carpeta entrega. No modifica main ni el sitio en producción.

## Actualización solicitada: MFA temporal

Parche incremental de 4 KB: [descargar JMAC-actualizacion-MFA.zip](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-actualizacion-MFA.zip).

Permite configurar JMAC_MFA_REQUIRED=false sin reinstalar la app. Sigue exigiendo correo/contraseña y permisos. Instrucciones y reactivación en [actualizaciones/MFA/LEEME.md](actualizaciones/MFA/LEEME.md). No incluye .env ni modifica la base de datos. 77 tests / 542 aserciones correctas, Pint correcto; aplicación del parche en cPanel pendiente.

## Actualización visual: instalación suspendida

El hosting rechazó `JMAC-actualizacion-visual.zip` con `Sanesecurity.Foxhole.JS_Zip_20.UNOFFICIAL`. Se retira el archivo de la entrega actual mientras se analiza la detección; no se considera aplicado ni limpio. No desactivar el antivirus ni cambiar el contenedor/extensiones para eludirlo.

[Informe y mensaje para soporte](actualizaciones/visual/ANTIVIRUS.md). Se comprobaron integridad, correspondencia con fuentes y compilación reproducible. El análisis antivirus queda pendiente: descarga de firmas bloqueada por el proxy. Estos controles no certifican ausencia de malware.

Los cambios de logo/carrito/editor siguen disponibles en el código local y documentados en [las instrucciones suspendidas](actualizaciones/visual/LEEME.md) y [capturas de pruebas funcionales](actualizaciones/visual/capturas/). El rechazo no reinstala ni modifica la aplicación del propietario.
