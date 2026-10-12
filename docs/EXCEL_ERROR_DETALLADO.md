# Corrección del aviso «Excel demasiado grande o con contenido activo»

Ese aviso lo genera JMAC después de recibir el archivo y revisar sus partes internas. Subir upload_max_filesize, post_max_size o memory_limit a 512M no elimina los límites del análisis ni identifica la causa.

Se corrigió una incompatibilidad comprobada: la regla anterior rechazaba todos los archivos .bin, incluidos los ajustes de impresión normales que Excel guarda en xl/printerSettings. Ahora se admiten exclusivamente los nombres estándar printerSettingsN.bin, con el tipo OOXML de impresora declarado y hasta 256 KB por parte. Esos datos se ignoran: no se leen, ejecutan, extraen, publican ni importan. Las macros, controles y otros binarios siguen rechazándose. La corrección no afirma que los ajustes de impresión sean la causa del archivo del propietario: el adjunto original supera el límite de transferencia de 32 MiB y no fue abierto íntegramente.

El aviso general se reemplazó por mensajes distintos para fotografías mayores de 8 MB, XML mayor de 10 MB, libro mayor de 256 MB descomprimidos, macros, conexiones externas, objetos incrustados, binarios no admitidos, cifrado o estructura inválida. Los límites no se aumentaron ni se desactivaron controles.

## Aplicar en cPanel

Esta corrección requiere la actualización **JMAC-Excel-con-fotografias.zip**, que incorpora el formato Nombre, Descripción, Categoría, Imagen, SKU. No sustituye el instalador ni incluye dependencias.

1. Descarga **JMAC-correccion-Excel.zip**.
2. En cPanel → Administrador de archivos, abre **/home/jmaccr**: allí están **jmac_app** y **public_html**.
3. Sube el ZIP, selecciónalo y pulsa **Extraer**. El destino debe ser **/home/jmaccr**.
4. Se actualiza únicamente jmac_app/app/Domain/Catalog/ExcelCatalog.php y se añade este manual. Se conservan .env, APP_KEY, usuarios, base de datos, storage, medios, MFA y recursos públicos. No hay migraciones ni comandos Terminal/Cron.
5. En JMAC → **Sistema → Diagnóstico → Vaciar caché**. Vuelve a la pantalla Excel y pulsa **Ctrl + F5**.
6. Selecciona nuevamente el Excel y pulsa **Analizar Excel y revisar cambios**. Los lotes fallidos anteriores conservan su error histórico: el análisis nuevo es el que utiliza la corrección.
7. Si aparece otro aviso, copia su texto completo. No confirmes Reemplazar hasta que la revisión sea válida y coincida con lo que deseas cargar.

Puedes conservar PHP en 512M; no es necesario seguir modificando esos valores por este aviso.

## Qué significa el nuevo resultado

- **Preparando fotografías / Por confirmar:** pasó el análisis. Revisa SKU, cantidades y fotos y continúa el flujo normal de aprobación/publicación y confirmación.
- **Una fotografía excede los 8 MB:** comprime las fotos grandes en Excel y guarda una copia. El límite también se aplica a la foto original guardada internamente, aunque se vea pequeña en la hoja.
- **Supera 256 MB al descomprimirlo:** el XLSX ocupa menos en disco que sus fotos y datos internos. Comprime las fotografías o reparte el inventario en archivos más pequeños. Usa Sumar para incorporar cada parte.
- **Macros / conexiones / objetos:** guarda una copia .xlsx con valores y fotos incrustadas, retirando los componentes que indique el aviso. Las fotografías normales en Imagen siguen admitidas.
- **XML o dimensiones excesivas:** copia únicamente encabezados y filas utilizadas a un libro nuevo o divide el inventario.

Los errores invalidan el lote completo antes de modificar productos, categorías, marcas o medios. Los mensajes no certifican que el archivo sea malicioso: indican qué parte no admite el importador.
