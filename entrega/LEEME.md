# JMAC 2.0 — versión oficial 1.0.0

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
