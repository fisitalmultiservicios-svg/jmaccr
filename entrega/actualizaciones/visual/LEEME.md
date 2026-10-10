# JMAC 2.0 — logo, carrito y edición sencilla

Actualización solicitada por el propietario el 10 de octubre de 2026.

## Cambios

- Logo oficial de `assets_limpios/logo-jmac.png`, integrado por petición expresa del dueño. Se conserva el archivo original sin modificar: SHA256 `a339683a62c4448e5e7ca594e83744c9d65082bafcd0332363c9ec9aebf9651d`. Se muestra en cabecera, pie y acceso/panel. En Diseño se puede elegir otro medio aprobado; una elección personalizada ya publicada se conserva.
- Carrito gráfico con contador y panel lateral accesible desde todas las páginas. Permite revisar productos, cambiar cantidades, retirar líneas y continuar con la solicitud de preventa. Conserva la lista al navegar. Continúa siendo preventa, sin precios públicos ni pasarela de pago.
- Inicio del panel con accesos a Editar mi web, Logo y colores, Mis productos y Solicitudes recibidas, según permisos.
- Constructor guiado: seleccionar una sección pulsando sobre la vista previa, editar campos pertinentes y guardar. Estilos, visibilidad, SEO, programación, versiones y secciones reutilizables quedan disponibles dentro de opciones desplegables. La publicación solicita guardar primero los cambios pendientes. Se mantiene la biblioteca de 21 tipos.
- Diseño global con logo/colores/letra/pie y muestra inmediata; guardar borrador y publicar siguen siendo operaciones separadas. Se evita publicar una versión anterior si hay cambios sin guardar.
- Crédito del pie predeterminado: «Creado por fisitalcr». Editable desde Logo, colores y pie → Crédito del creador, con revisiones/publicación y escape de HTML.

No se importan productos, categorías, marcas, preventas ni noticias. No se publican páginas corporativas ni fotografías candidatas automáticamente. No hay nuevas migraciones. La configuración privada, claves, usuarios, datos y la decisión previa sobre MFA se conservan.

## Aplicar en el cPanel del propietario

1. Descarga `JMAC-actualizacion-visual.zip` desde la rama de entrega de GitHub.
2. En Administrador de archivos abre `/home/jmaccr`, donde están `jmac_app` y `public_html`. Sube el ZIP y usa Extraer / Extract, con destino `/home/jmaccr`.
3. El ZIP actualiza rutas concretas dentro de `jmac_app` y `public_html`, incluidos logo y recursos CSS/JS ya compilados. No contiene `.env`, claves, vendor, base de datos ni instaladores. No ejecutes de nuevo la instalación ni las semillas.
4. En el panel JMAC abre Sistema → Diagnóstico y pulsa **Vaciar caché**. Se limpian las vistas compiladas para que aparezcan las plantillas nuevas; el botón es real y exige permiso y CSRF.
5. Recarga el navegador con Ctrl+F5. Abre `https://jmaccr.com/admin` para editar y `https://jmaccr.com/` para ver el sitio, usando el archivo hosts de Windows que dirige al servidor nuevo.
6. En el panel elige **Editar mi web** y una página. Pulsa una sección de la vista previa y modifica su texto. Guarda, comprueba la vista previa y publica solo lo que apruebes. Para el crédito: **Logo y colores → Crédito del creador → Guardar diseño en borrador → Publicar este diseño**.

El carrito estará visible aunque el catálogo esté vacío. Solo tendrá productos cuando crees y publiques productos nuevos desde Mis productos.

## Comprobación y recuperación

La actualización se prueba contra SQLite en memoria y en navegador con una MariaDB `jmac_visual_test` separada. Los datos nuevos del navegador se eliminan junto con esa base al terminar. Las capturas con productos QA documentan pruebas; no son datos a importar.

Comandos:

```sh
source /workspace/toolchain/activate
npm run build
php artisan test
python3 tests/Browser/visual_update.py
vendor/bin/pint --test app/Domain/Site/Site.php app/Http/Controllers/Admin/ThemeController.php tests/Feature/CmsTest.php tests/Feature/PublicSiteTest.php
```

Para volver a la presentación anterior, restaura únicamente los archivos enumerados en `CHECKSUMS_VISUAL_SHA256.txt` desde el paquete/copia anterior y vacía las vistas compiladas. No sustituyas `.env`, datos ni los archivos del parche de MFA. El logo nuevo no requiere ningún proceso Node en producción.

Las pruebas locales no certifican la aplicación del parche en cPanel ni constituyen aprobación de una réplica visual exacta. El propietario verificará el resultado después de extraerlo.

Resultados: 78 tests / 567 aserciones correctas; Vite build y Pint correctos. Navegador real: logo cargado, carrito vacío y con altas nuevas QA, cantidades/retirada/persistencia, Escape y retorno de foco, selección pulsando la vista previa, guardado y recarga, estilos persistidos en bloques nuevos, borradores aislados, publicación de crédito editable y cinco anchos (360/390/768/1024/1440), sin errores JavaScript. Base MariaDB QA eliminada. Capturas y reporte en docs/evidence/visual-*.
