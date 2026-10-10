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
