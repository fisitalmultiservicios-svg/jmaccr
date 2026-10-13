# JMAC 2.0 — versión oficial 1.0.0

## Corrección de 409 al preparar fotografías

[Descargar JMAC-Excel-preparacion.zip (17 KB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-Excel-preparacion.zip).

Para la instalación con Excel/fotografías. Las peticiones se serializan por la fila InnoDB y el cliente evita que el botón y el temporizador se envíen simultáneamente. Un retry sobre un estado terminal vuelve a la revisión; timeout/deadlock MySQL concreto es reintentable. Permisos, CSRF, avances, confirmación, copia y catálogo se conservan. No depende del flock de preparación del hosting.

Sube y extrae en /home/jmaccr; Sistema → Diagnóstico → Vaciar caché. Retoma https://jmaccr.com/admin/catalog/imports/5, sin /prepare, y pulsa Ctrl+F5. Si el lote venció tras dos horas, carga el Excel nuevamente. El parche actualiza dos PHP y la vista, añade manual/checksums y conserva .env, BD, storage, fotos, MFA, vendor y assets. No reinstala, no tiene migraciones ni archivos JS y no modifica PHP INI.

[Manual](actualizaciones/excel-preparacion/LEEME.md) · [Paquete](actualizaciones/excel-preparacion/EVIDENCIA.json) · [Concurrencia real](actualizaciones/excel-preparacion/concurrencia.json) · [Navegador runtime](actualizaciones/excel-preparacion/navegador-paquete.json).

104 tests / 953 aserciones SQLite y 26 / 376 MariaDB correctos; Pint correcto. Dos procesos reales avanzan 10→12 fotos; timeout InnoDB conserva avance y otros fallos SQL se propagan. Navegador fuente/runtime con doble submit y primera conexión lenta: dos POST para 12 fotos, miniaturas, retry y cancelación correctos, cero errores JS. ZIP aplicado con unzip nativo conserva archivos/permisos; bases QA eliminadas. Sin acceso remoto al hosting ni prueba integral del Excel original. Main permanece intacta.


## Corrección del aviso general al analizar Excel

[Descargar JMAC-correccion-Excel.zip (11 KB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-correccion-Excel.zip).

Para instalaciones con la actualización de fotografías aplicada. Admite ajustes de impresora estándar que Excel guarda como .bin, sin leerlos/extraerlos/ejecutarlos. Conserva los límites y rechazos de macros, conexiones, objetos y archivos no admitidos. Ahora el mensaje identifica la causa concreta de tamaño/contenido; no supone que el archivo original sea malicioso.

Sube y extrae en /home/jmaccr. Pulsa Sistema → Diagnóstico → Vaciar caché, Ctrl+F5 y analiza nuevamente el archivo. Actualiza un solo PHP y añade manual/checksums; conserva configuración/BD/storage/vendor/recursos públicos/MFA. Sin migraciones ni JS ni cambios de PHP INI. Los lotes fallidos anteriores conservan su mensaje histórico.

[Manual](actualizaciones/excel-validacion/LEEME.md) · [Paquete](actualizaciones/excel-validacion/EVIDENCIA.json).

101 tests / 906 aserciones SQLite; 23 / 329 MariaDB; Pint: correctos. ZIP aplicado con unzip nativo sobre runtime cPanel: datos/configuración/permisos conservados, fotos/SKU asociados y error específico comprobado. BD de pruebas eliminada. El adjunto completo del propietario sigue sin poder inspeccionarse por el límite de transferencia del cloud; aplicación en hosting pendiente. Main no cambia.


## Actualización: inventario Excel con fotografías

[Descargar JMAC-Excel-con-fotografias.zip (42 KB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-Excel-con-fotografias.zip).

Acepta Nombre, Descripción, Categoría, Imagen, SKU, con fotos incrustadas en la columna Imagen. Preparación automática en grupos pequeños, vista previa de fotos, aprobación/publicación explícitas y Sumar/Reemplazar con copia/transacción/histórico conservado. Formato OOXML anclado y Excel 365 dentro de celda. No descarga URLs.

Sube el parche a /home/jmaccr, extráelo allí y pulsa Sistema → Diagnóstico → Vaciar caché. No reinstala ni cambia .env/BD/storage/MFA/vendor/assets; no incluye archivos JS ni nuevas migraciones. Para un archivo grande, en cPanel configura PHP upload_max_filesize=128M, post_max_size=144M (o 256M si el menú solo ofrece esa opción), memory_limit=256M. El panel muestra el límite real; el ZIP no cambia INI automáticamente.

[Manual paso a paso](actualizaciones/excel-fotografias/LEEME.md) · [Evidencia de paquete](actualizaciones/excel-fotografias/EVIDENCIA.json) · [Capturas](actualizaciones/excel-fotografias/capturas/).

98 tests / 798 aserciones SQLite y 20 / 221 MariaDB correctos. ZIP real probado contra public_html/vendor runtime con un Excel nuevo de 37.794.674 bytes: fotos, preparación automática, aprobación/publicación, Sumar/Reemplazar y carrito en cinco anchos, cero errores JS; BD QA eliminada. No se probó completo el adjunto original: supera el límite de transferencia de 32 MiB del cloud. Se comprobó el formato confirmado con archivos nuevos equivalentes. Aplicación/INI/QA del hosting pendientes; main permanece intacta.


## Corrección posterior: eliminar el hueco del catálogo

[Descargar JMAC-ajuste-espacio-catalogo.zip (20 KB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-ajuste-espacio-catalogo.zip).

Después de instalar la versión oficial 1.0.0, sube este parche a /home/jmaccr, extráelo allí y pulsa Sistema → Diagnóstico → Vaciar caché; recarga Catálogo con Ctrl+F5. Elimina el título automático y los bloques técnicos vacíos de la página publicada. Catálogo, búsqueda y carrito quedan debajo del menú con 24 px de separación. No cambia la base de datos, Excel, configuración, usuarios ni JavaScript. No repitas el instalador.

[Manual](actualizaciones/espacio-catalogo/LEEME.md) · [Evidencia](actualizaciones/espacio-catalogo/EVIDENCIA.json) · [Capturas reales](actualizaciones/espacio-catalogo/capturas/).

Probado el ZIP real sobre el integral oficial con unzip nativo: archivos privados conservados, CSS/manifiestos resueltos, cinco anchos y catálogo vacío/con producto nuevo; carrito real, sin errores JS, base QA eliminada. Aplicación por el propietario pendiente. El código fuente de la rama oficial ya incluye la corrección; los ZIP 1.0.0 anteriores se conservan y requieren aplicar este parche después.


Preparada el 12 de octubre de 2026. El propietario aprobó el diseño reparado y solicitó la retirada del banner de cuatro páginas y la importación Excel; esta versión implementa ambos.

## Tu instalación actual: actualizar sin reinstalar

[Descargar actualización oficial (612 KB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-2.0-PRODUCCION-1.0.0-ACTUALIZACION.zip).

1. En Sistema → Backups crea y descarga una copia privada; conserva también copia de los archivos actuales fuera de public_html.
2. Sube el ZIP a /home/jmaccr y extráelo en /home/jmaccr, donde están jmac_app y public_html.
3. En Sistema → Diagnóstico pulsa Vaciar caché y recarga con Ctrl+F5. Debe indicar Versión oficial 1.0.0.
4. Revisa APP_ENV=production y APP_DEBUG=false en tu .env privado, conserva APP_KEY/BD/usuarios/MFA y vuelve a vaciar caché.
5. Catálogo → Productos y especificaciones → Cargar productos desde Excel. Descarga plantilla, elige Sumar o Reemplazar, analiza y confirma la vista previa.

No contiene .env/storage/base de datos ni nuevas migraciones. No requiere Composer, Node, cron de instalación ni otra BD. El JavaScript existente no cambia; el ZIP incremental no incluye JS. Archivos públicos 0644/directorios 0755. Sumar actualiza códigos coincidentes y conserva ausentes; Reemplazar retira ausentes a papelera con copia cifrada previa y conserva histórico de preventas.

[Manual detallado](produccion-1.0.0/LEEME.md) · [Guía Excel](produccion-1.0.0/IMPORTACION_EXCEL.md) · [Código fuente completo](https://github.com/fisitalmultiservicios-svg/jmaccr/tree/jmac-2-0-produccion-1-0-0).

## Instalaciones nuevas

[Paquete completo oficial (20 MB)](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-2.0-PRODUCCION-1.0.0-COMPLETO.zip). Lee el manual: no extraer este integral sobre la instalación existente. Dependencias PHP runtime y assets build incluidos; cero catálogo/solicitudes/artículos iniciales y semilla corporativa borrador. El hosting puede rechazar archivos por antivirus: no se certifica aceptación del integral ni se desactiva el escáner.

Verificación: 90 tests / 705 aserciones SQLite; 14 / 183 de importación/páginas en MariaDB. ZIP real probado en navegador (Excel, banner, logo/carrito/editor/caché y cinco anchos); integral instalado desde cero con MFA y 18 pantallas en ambas estructuras públicas. Bases QA eliminadas, build/Pint/auditorías correctos. [Evidencia de entrega](produccion-1.0.0/ENTREGA_VERIFICADA.json), [pruebas](produccion-1.0.0/pruebas.json) y [capturas reales](produccion-1.0.0/capturas/).

La publicación DNS, recepción SMTP y QA del hosting siguen pendientes; esta entrega no realiza cambios remotos. No afirma réplica exacta ni 48/48 aprobación externa. main conserva su commit original.

## Entregas anteriores (referencia histórica)


## Reparar estilos y logo después de la actualización

Se encontró y corrigió un error en el ZIP por partes: los archivos públicos tenían permisos 0600. La extracción Python usada en la primera prueba no restauraba esos permisos y no detectó el fallo en hosting compartido. La entrega actual tiene archivos 0644 y directorios 0755 explícitos.

Si ya instalaste la primera versión y la web aparece sin diseño, descarga [JMAC-reparacion-estilos-logo.zip](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-reparacion-estilos-logo.zip) y extráelo en /home/jmaccr. Contiene solo CSS/logo/manifiestos en ambos directorios públicos; no incluye JavaScript, .env, base de datos ni PHP. [Pasos y permisos de carpetas](actualizaciones/reparacion-estilos/LEEME.md).

Se probó con unzip nativo: error 0600 reproducido, lectura de CSS/PNG reparada y datos/configuración/índices conservados. Unzip conserva permisos de carpetas existentes: se probó y documentó el ajuste manual 0755 para una carpeta pública restrictiva. Navegador: estilos efectivos, logo, carrito/editor y cinco anchos correctos. [Evidencia](actualizaciones/reparacion-estilos/EVIDENCIA.json). La aplicación en el hosting todavía debe comprobarse.


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

## Instalación mediante archivos individuales

El propietario no dispone de soporte del hosting. Se entrega una alternativa que mantiene el antivirus activo y el JavaScript visible con su extensión normal; cada subida debe ser aceptada por el escáner antes de aplicar la actualización.

- [JMAC-visual-PHP-CSS-logo.zip](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/JMAC-visual-PHP-CSS-logo.zip): archivos PHP, plantillas, CSS y logo; sin archivos JavaScript.
- [app-B9eZnYus.js](https://github.com/fisitalmultiservicios-svg/jmaccr/raw/refs/heads/entrega-jmac-2-0/entrega/actualizaciones/visual-por-partes/app-B9eZnYus.js): script compilado para subir directamente a ambas carpetas de assets.

[Pasos con las carpetas exactas](actualizaciones/visual-por-partes/LEEME.md). Analizar primero ambos archivos con Windows Defender actualizado. Si cualquier antivirus rechaza un archivo, detener la instalación. No usar el ZIP anterior retirado.

Probado sobre una copia del release anterior y el parche MFA, con el public_html real de esa estructura: editor/carrito/logo/pie y cinco anchos correctos, sin errores JS; BD QA eliminada. .env/storage/índices/MFA conservados y manifiestos resueltos. [Evidencia](actualizaciones/visual-por-partes/EVIDENCIA.json). Aceptación antivirus e instalación en el hosting pendientes; no se afirma un falso positivo confirmado.
