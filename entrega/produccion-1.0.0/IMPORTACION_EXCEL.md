# Productos desde Excel — JMAC 2.0

En **Catálogo → Productos y especificaciones**, pulsa **Cargar productos desde Excel**. Descarga la plantilla, completa la primera hoja y pulsa **Analizar Excel**. La plantilla contiene encabezados e instrucciones, cero productos.

SKU y Nombre son obligatorios. Las columnas admitidas son SKU (o Código), Nombre, Categoría, Marca, Resumen, Descripción, Unidad, Estado, Existencias, Precio interno y Etiquetas. SKU se guarda en mayúsculas y debe estar en formato Texto en Excel para conservar sus ceros iniciales. No se importan fotografías, PDF ni direcciones externas: los medios se administran y aprueban en el panel.

El límite es 5 MB, 2.000 filas de productos y 30 columnas, con máximo 16 MB descomprimidos. Se rechazan filas duplicadas por SKU, fórmulas, macros, entidades XML, relaciones externas, libros mal formados y dimensiones excesivas. Usa `.xlsx` y pega solo valores. Se lee únicamente la primera hoja. No se admiten `.xls`, `.xlsm` ni CSV históricos; el archivo debe contener información nueva de JMAC 2.0.

## Sumar al catálogo

Agrega los SKU nuevos y actualiza los coincidentes. Conserva los productos ausentes. Mantiene IDs, URLs y medios de los SKU existentes, y el histórico de preventas no cambia.

Una columna omitida conserva el valor anterior. Una celda vacía dentro de una columna presente borra ese dato, salvo Unidad (unidad) y Estado (borrador). Existencias sustituye el número anterior; no suma unidades. Las etiquetas se separan por comas. Precio interno permanece privado y no habilita pagos ni precios públicos.

## Reemplazar el catálogo

Deja activos únicamente los SKU del Excel. Los productos ausentes se retiran a papelera; sus registros e imágenes siguen disponibles para el histórico y restauración. Las categorías y marcas no utilizadas se ocultan. Las categorías relacionadas con los productos y sus padres se conservan. Las preventas, líneas con snapshots, eventos y contactos no se borran.

La revisión muestra Nuevos, Actualizados, Restaurados, Retirados y Errores. Si hay errores, ninguna fila se aplica. Para reemplazar, marca la autorización y escribe **REEMPLAZAR**. Antes de confirmar, se crea una copia privada cifrada en Sistema → Backups. Si la copia falla, se detiene la operación sin cambiar productos.

## Publicación y permisos

Estado admite borrador, publicado, oculto o archivado. Publicado exige permiso `products.publish`. Crear o actualizar agrupaciones requiere sus permisos correspondientes; sus medios y los de productos coincidentes deben estar aprobados antes de publicar. Se puede confirmar un archivo en borrador y publicar después desde las fichas.

Cargar Excel exige `products.view`, `products.create` y `products.update`. Reemplazar exige además `products.delete`, `categories.update` y `brands.update`. Cada lote está vinculado al usuario que lo subió; otro usuario no puede verlo ni confirmarlo. Auth, la configuración vigente de MFA, CSRF y rate limits siguen activos.

Las vistas previas se guardan cifradas fuera de public_html y duran dos horas. Aplicar, cancelar o vencer elimina el payload. Se conservan el lote, sus contadores, errores y auditoría. Reenviar una confirmación aplicada no duplica productos. Si el catálogo cambia después de analizar, hay que analizar nuevamente: no se sobrescriben cambios posteriores inadvertidamente.

La carga y aplicación se realizan con PHP en peticiones finitas; no requieren Node, workers ni Composer en el hosting.
