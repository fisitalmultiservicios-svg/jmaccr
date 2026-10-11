# Revisión pendiente del antivirus del hosting

El hosting rechazó `JMAC-actualizacion-visual.zip` al subirlo y notificó:

```text
Sanesecurity.Foxhole.JS_Zip_20.UNOFFICIAL FOUND
```

La actualización visual queda suspendida. No se considera aplicada en cPanel ni aprobada por el antivirus. No volver a subir el mismo archivo, desactivar el escáner, ocultar extensiones o cambiar el contenedor para eludir la detección.

Actualización posterior: el dueño informó que no dispone de soporte. Se preparó una [instalación mediante archivos individuales](../visual-por-partes/LEEME.md): el script se sube directamente con su extensión `.js` y pasa por el antivirus, mientras PHP/CSS/logo se entregan en un ZIP separado. No se usa otra extensión, contenedor cifrado ni descarga desde PHP. La instalación depende de que el escáner acepte cada archivo; no confirma que el aviso anterior fuera un falso positivo. Los resultados anteriores se conservan como registro histórico.

## Evidencia comprobada

- Archivo de 125.580 bytes, 29 entradas y CRC correcto.
- SHA256: `b2aa5dd6feb85d4faf8b6b0c2ab30cccefa297336b06c6671ba363e46f0c6b73`.
- El archivo descargado desde GitHub coincide byte por byte con el preparado localmente.
- Las 28 entradas de contenido coinciden con los archivos de la aplicación y su documentación. La entrada restante es el listado de SHA256; no contiene código.
- Al ejecutar de nuevo `npm run build`, JavaScript, CSS y manifiesto reproducen exactamente los recursos del ZIP rechazado. No se ha cambiado la aplicación ni se ha generado un paquete alternativo para evitar el antivirus.
- El logo coincide con el PNG corporativo limpio aprobado por petición expresa del propietario.
- No se incluyen `.env`, claves, base de datos, respaldos WordPress, dependencias de producción ni instaladores. La actualización no contiene migraciones.

Estos resultados verifican procedencia e integridad; **no certifican ausencia de malware**. Los 78 tests y pruebas de navegador documentados en la actualización validan funcionamiento, no sustituyen un análisis antivirus.

## Límite del análisis

Se preparó ClamAV 1.4.3 desde paquetes Debian comprobados con los SHA256 del índice firmado. La descarga de las bases de ClamAV y Sanesecurity recibió una denegación del proxy HTTP 403. No se modificaron restricciones de red ni se desactivaron verificaciones. No se ejecutó un escaneo sin firmas ni se declaró el archivo limpio.

El proyecto de actualización de firmas [clamav-unofficial-sigs](https://github.com/extremeshok/clamav-unofficial-sigs/blob/948a1fb84c15a71910456561cec384a978b13412/config/master.conf#L232) identifica reglas Foxhole para JavaScript y las clasifica por riesgo de falsos positivos. Esto justifica solicitar una revisión, pero no permite afirmar que esta detección concreta sea un falso positivo: falta comprobar la definición exacta y el resultado del hosting.

El informe con los SHA256 de cada archivo está en `docs/evidence/visual-antivirus-integrity.json`.

## Mensaje para soporte del hosting

> cPanel rechazó mi actualización web JMAC-actualizacion-visual.zip con «Sanesecurity.Foxhole.JS_Zip_20.UNOFFICIAL FOUND». Solicito una revisión del archivo para determinar si hay malware o si coincide con una regla general para JavaScript dentro de ZIP. Por favor indiquen el archivo interno detectado, la definición exacta de la firma y el resultado del análisis. No solicito desactivar el antivirus. Tamaño: 125.580 bytes. SHA256: b2aa5dd6feb85d4faf8b6b0c2ab30cccefa297336b06c6671ba363e46f0c6b73.

La muestra original se conserva en el historial de GitHub para que soporte pueda revisarla; su enlace debe utilizarse solo para análisis, no para instalar:

`https://github.com/fisitalmultiservicios-svg/jmaccr/raw/40867c0ab15447874da0bbcebfe1981028baa497/entrega/JMAC-actualizacion-visual.zip`

Una detección real exige retirar o sustituir el código afectado y repetir análisis y pruebas. Si soporte confirma un falso positivo, se seguirá su procedimiento de corrección o aprobación específica, manteniendo el antivirus activo. La instalación actualmente abierta, los datos y la configuración MFA no se modifican por este rechazo.
