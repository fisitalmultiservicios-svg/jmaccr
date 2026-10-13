# Preparación de fotografías: corrección del error 409

La pantalla del propietario mostró «409 Conflict» en /admin/catalog/imports/5/prepare. Esa ruta prepara fotografías después del análisis del libro; el error observado no corresponde al límite PHP de subida.

El código anterior intentaba un bloqueo no bloqueante de archivo y devolvía 409 al encontrar otra preparación en curso. Además, el botón manual y el temporizador automático podían enviar la misma preparación: navegar con el botón cancelaba la petición del navegador, pero la preparación anterior podía seguir ejecutándose en el servidor. No se afirma acceso remoto a los logs ni que se haya confirmado ese disparador exacto en el hosting.

Ahora se usa exclusivamente el bloqueo de fila InnoDB de la transacción para serializar el estado y el índice de fotos. Una segunda petición espera el grupo finito anterior y lee el avance actualizado. El bloqueo de archivo dejó de utilizarse para preparar; la confirmación del catálogo conserva sus protecciones existentes. Un timeout/deadlock MySQL concreto vuelve a la revisión con un reintento automático a los cinco segundos; otros fallos de BD siguen visibles. Repetir preparación sobre un lote listo, aplicado, cancelado, inválido o vencido devuelve su revisión sin reprocesarlo. Se mantienen Auth, propietario del lote, roles, permisos de medios y CSRF.

En la pantalla de preparación, el primer envío desactiva el botón y cancela el temporizador; los siguientes envíos se evitan. Cancelar detiene ese temporizador. Al volver a una página guardada en la caché del navegador se recarga el estado actual. No se necesita Node, Cron ni un proceso persistente.

## Actualizar y retomar el archivo del propietario

Requiere la actualización **JMAC-Excel-con-fotografias.zip**. Conserva la corrección previa **JMAC-correccion-Excel.zip**; este ZIP no vuelve a sustituir ExcelCatalog ni sus avisos precisos.

1. Descarga **JMAC-Excel-preparacion.zip**.
2. En cPanel → Administrador de archivos, abre **/home/jmaccr**, donde están **jmac_app** y **public_html**.
3. Sube el ZIP y extráelo en **/home/jmaccr**. Actualiza dos archivos PHP y la vista de revisión; añade este manual/checksums. No cambia .env, claves, usuarios, BD, storage, fotos, MFA, recursos compilados ni PHP INI. No hay migraciones ni reinstalación.
4. En JMAC → **Sistema → Diagnóstico → Vaciar caché**.
5. Abre **https://jmaccr.com/admin/catalog/imports/5**. Esa dirección muestra la revisión; no escribas /prepare en la barra de direcciones porque esa ruta es una acción POST.
6. Pulsa **Ctrl + F5** y deja la pantalla abierta. El avance continuará automáticamente; no hace falta pulsar el botón repetidamente. Los grupos preparados se conservan.
7. Cuando aparezca **3. Confirma los cambios**, revisa productos y miniaturas. Aprueba fotografías/publicación solo si procede y confirma Sumar o Reemplazar según la revisión.

Si la revisión dice **vencida**, el temporal expiró tras dos horas: carga el mismo Excel nuevamente desde el botón normal. Si dice **inválida**, copia el mensaje detallado de la tabla para resolverlo. No se confirma ningún cambio al catálogo desde la preparación.

## Verificación

Pruebas HTTP con un bloqueo de archivo legado mantenido, avance 10→12 fotos, reintentos después de listo/aplicado/cancelado/inválido/vencido y permisos/CSRF. Concurrencia real con dos procesos PHP y bloqueo de fila MariaDB: el segundo espera, termina el índice 12 y no crea productos/medios en previa. Se provocó un timeout InnoDB real, se comprobó la respuesta reintentable y se verificó que un error SQL ajeno al bloqueo sigue lanzándose.

Chromium contra la app fuente y el paquete runtime cPanel: doble envío manual junto al temporizador, conexión inicial retrasada, dos peticiones reales para 12 fotos, miniaturas visibles, retry listo y cancelación sin preparación automática adicional. Evidencia en docs/evidence/excel-preparation-*. Pruebas con datos nuevos en bases descartables eliminadas; no se aplicó la actualización al hosting ni se procesó completo el Excel original del propietario.
