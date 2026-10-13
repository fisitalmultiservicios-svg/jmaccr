# Formularios sin aceptación obligatoria y cantidad antes del carrito

Cambio solicitado por el propietario el 13 de octubre de 2026. Consultas y preventas ya no exigen aceptar la política, indicar su versión ni tener un documento legal aprobado para funcionar. El documento de privacidad sigue siendo editable/publicable de forma independiente; no se publica texto inventado. Los formularios conservan Nombre/Teléfono y las validaciones propias, CSRF, antispam, límites, cifrado de datos y permisos del backend. Correo/Empresa continúan opcionales.

Las solicitudes nuevas registran **sin_aceptacion_requerida** en la columna histórica consent_version para indicar que no se pidió aceptación; no se marca un consentimiento ficticio. En el Admin aparece «Aceptación no requerida». Las solicitudes anteriores, sus snapshots y versiones aceptadas se conservan. Un reintento de la misma solicitud anterior compara sus datos protegidos y la sesión; no duplica solicitudes ni notificaciones ni admite cantidades/datos distintos con el mismo token. Esta compatibilidad se refiere a solicitudes propias de JMAC 2.0 anteriores a este cambio, no a datos WordPress.

Cada tarjeta del catálogo y la ficha del producto incluyen **Cantidad** antes de **Cargar al carrito**. El carrito añade exactamente la cantidad elegida, suma si el producto ya estaba en la lista y conserva el carrito guardado en el navegador. Una cantidad fraccionaria, cero o superior al máximo no se añade ni se recorta silenciosamente. Se puede editar después en el carrito. El máximo se configura en **Sistema → Configuración → Cantidad máxima por producto** y se valida también en servidor.

## Actualizar en cPanel

Descarga los dos archivos: **app-CsHK9iZX.js** y **JMAC-privacidad-cantidades.zip**. El JavaScript compilado se entrega como archivo .js visible e individual; el ZIP contiene PHP/vistas/CSS/manifiestos/manual, sin archivos .js. Conserva el antivirus activo; no se certifica su resultado ni se oculta código.

1. Guarda **app-CsHK9iZX.js** en tu equipo. Si el navegador abre el código, vuelve al enlace y pulsa botón derecho → **Guardar enlace como**. Conserva exactamente el nombre y la extensión .js.
2. En cPanel → Administrador de archivos abre **/home/jmaccr/public_html/build/assets** y carga el archivo .js.
3. Carga ese mismo archivo también en **/home/jmaccr/jmac_app/public/build/assets**. Déjalo en ambas carpetas. Los archivos JS anteriores pueden permanecer para páginas que ya estaban abiertas.
4. Abre **/home/jmaccr**, donde están jmac_app y public_html. Sube **JMAC-privacidad-cantidades.zip** y extráelo en **/home/jmaccr**. Hazlo después de cargar el JS para que el manifiesto apunte a un archivo que ya existe.
5. Entra por **https://jmaccr.com/admin/login** y pulsa **Sistema → Diagnóstico → Vaciar caché**. Recarga con **Ctrl + F5**.
6. En Catálogo elige una cantidad y pulsa Cargar al carrito: el contador y la línea deben reflejarla. En Contacto y la solicitud de preventa ya no aparece la casilla obligatoria. La confirmación muestra consecutivo solo después de guardar en BD.

No se reinstala, no se crean tablas ni se modifica .env/APP_KEY/BD/usuarios/storage/MFA. Se conservan ajustes visuales, importador Excel y reparación de HTTP/HTTPS. No hay que cambiar DNS, PHP ni volver a ejecutar el instalador. Si el JS se rechaza por el antivirus, comunica el aviso exacto; no cambies su extensión ni desactives el escaneo.

## Validación

Se prueban formularios sin política y sin campos de aceptación, metadatos veraces, idempotencia nueva y anterior, cantidades inválidas, validación CSRF, cifrado, límites y permisos. Navegador con catálogo y ficha: 3 + 2 unidades = 5 guardadas en la preventa, exceso/fracciones rechazados, edición y carrito persistente, contacto/preventa reales sin casilla, reintento sin duplicado, dos mensajes reales pendientes en outbox, cinco anchos sin desbordamiento ni errores JS. Datos nuevos solo en bases descartables eliminadas. Unzip nativo y copia runtime cPanel verifican superposición sin datos/configuración perdidos. Evidencia cart-privacy-*. Actualización remota pendiente del propietario.
