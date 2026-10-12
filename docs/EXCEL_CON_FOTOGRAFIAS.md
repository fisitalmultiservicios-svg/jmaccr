# Inventario Excel con fotografías — formato JMAC

Formato confirmado por el propietario: **Nombre | Descripción | Categoría | Imagen | SKU**. Las fotografías aparecen directamente en Excel. Se aceptan fotos ancladas a la columna Imagen (una/dos celdas de anclaje OOXML) y fotos de Excel 365 colocadas dentro de celda (rich data/valueMetadata). La plantilla descargable ahora usa esas cinco columnas y SKU en formato Texto. Los encabezados anteriores de la plantilla ampliada siguen siendo válidos.

El archivo original `catalogo_jmac_unificado_sku_imagenes.xlsx` supera el límite de transferencia de 32 MiB del entorno de desarrollo y no pudo abrirse aquí. Se implementó el formato indicado por el propietario, con archivos nuevos generados con Excel/OpenXML equivalente. No se afirma una ejecución sobre todas las filas del adjunto original ni se importó su catálogo en una base real.

## Actualizar la instalación existente

1. Descarga **JMAC-Excel-con-fotografias.zip**. En cPanel → Administrador de archivos, sube el ZIP a **/home/jmaccr**, donde están jmac_app y public_html.
2. Selecciona el ZIP → Extraer → destino **/home/jmaccr**. Solo actualiza PHP/vistas/rutas. Conserva .env, claves, usuarios, base de datos, storage, MFA y el ajuste visual del catálogo. No hay migraciones ni nuevas dependencias Composer.
3. En JMAC → **Sistema → Diagnóstico → Vaciar caché**. Recarga con Ctrl+F5.
4. Antes de subir un archivo grande, abre cPanel → **MultiPHP INI Editor / Editor INI de MultiPHP**, selecciona jmaccr.com y configura **upload_max_filesize=128M**, **post_max_size=144M** y **memory_limit=256M**. Si tu panel usa **Seleccionar versión de PHP / Select PHP Version**, busca **Opciones / Options**. Un menú que no ofrece 144M puede usar 256M para post_max_size. Guarda y vuelve al panel JMAC: la pantalla de carga muestra el límite efectivo real. Esta actualización no cambia esos valores del hosting automáticamente.
5. En **Catálogo → Productos y especificaciones → Cargar productos desde Excel**, selecciona tu archivo, elige Sumar o Reemplazar y marca la declaración de datos nuevos. Pulsa **Analizar Excel y revisar cambios**.
6. Mantén abierta la pantalla mientras prepara las fotografías. El procesamiento continúa automáticamente en peticiones pequeñas, de hasta diez fotos y unos cinco segundos de trabajo por petición. Si el navegador no ejecuta JavaScript, el botón **Continuar preparación de fotografías** permite avanzar. No necesita Cron, Node ni workers persistentes.
7. Revisa cantidades, SKU y miniaturas. Para mostrarlas públicamente, marca **Confirmo que tengo permiso para usar estas fotografías…**. Marca **Publicar los productos de este Excel…** si deseas publicarlos al terminar. Si no marcas publicación, los nuevos quedan en borrador; si no apruebas fotografías, permanecen como candidatos privados. Cada opción exige sus permisos reales de servidor.
8. Marca la confirmación general. Para Reemplazar, escribe **REEMPLAZAR**. Al confirmar se crea una copia cifrada y se guarda en transacción. Los ausentes pasan a papelera; las preventas y snapshots se conservan. Un reintento no duplica productos/fotos. Una fotografía coincidente se reutiliza; las nuevas se colocan al principio de la galería conservando las anteriores.

No añadas enlaces, rutas locales ni fórmulas IMAGE/DISPIMG a Imagen: no se descargan ni ejecutan. La fotografía debe estar incrustada y asociada a la fila de su SKU. El análisis devuelve errores antes de modificar el catálogo si no puede asociarla.

## Límites y almacenamiento

Si aparece «Excel demasiado grande o con contenido activo» aunque PHP esté en 512M, aplica la corrección JMAC-correccion-Excel.zip siguiendo EXCEL_ERROR_DETALLADO.md. Admite los ajustes normales de impresión internos sin ejecutarlos y reemplaza ese aviso general por la causa específica. No aumenta ni elimina límites de seguridad.

Hasta **128 MB**, sujeto al PHP del hosting; hasta **2.000 filas de productos**, **30 columnas** y **cinco fotos por SKU**. Cada foto JPEG/PNG/WebP (AVIF si GD lo admite) puede tener hasta **8 MB y 20 megapíxeles**. Máximo 256 MB descomprimidos del libro y 16 MB de XML; se rechazan macros, entidades, fórmulas, vínculos externos, rutas inseguras, partes duplicadas y archivos ajenos a XLSX/medios admitidos.

Las fotos se decodifican con GD y se guardan como WebP, con lado mayor de 1920 px y miniatura de 480 px. Nunca se sirve el fichero original ni sus metadatos. El libro temporal está fuera de public_html, con permisos 0600; datos normalizados y fotos preparadas están cifrados. Aplicar, cancelar, vencer o detectar un error elimina los temporales. La vista previa no crea productos, categorías ni registros de medios. Una falla SQL retira los archivos nuevos y revierte toda la transacción.

Diagnóstico comprueba GD/WebP y las extensiones DOM/XMLReader/ZIP. Si PHP rechaza un POST grande, JMAC muestra una explicación en español; el catálogo permanece intacto. No uses migrate:fresh ni cambies APP_KEY para activar esta función.

## Validación

Pruebas reales de OOXML clásico y Excel 365, filas vacías intermedias y SKU con ceros; preparación finita, aprobación/publicación con permisos, miniaturas privadas, idempotencia, cancelación/vencimiento, imagen corrupta y fallo SQL sin archivos huérfanos. El navegador verifica subida de un libro nuevo de más de 32 MB, preparación automática, Sumar/Reemplazar, fotos públicas aprobadas y carrito. Los datos de prueba solo se usan en bases descartables *_test y se eliminan. Resultado y capturas en docs/evidence/excel-photos-*.
