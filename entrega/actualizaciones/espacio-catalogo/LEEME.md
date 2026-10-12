# Quitar el espacio vacío del catálogo

Corrección visual solicitada el 12 de octubre de 2026, después de la versión oficial 1.0.0. La página publicada podía mostrar un título automático y dos bloques técnicos vacíos antes del catálogo: el índice de categorías y un CTA sin texto derivado del carrito semilla. Aunque el banner de las páginas sin contenido se había retirado, esta variante publicada todavía dejaba un hueco grande.

La vista del catálogo omite ese título visible y esos contenedores técnicos. Categorías, filtros, productos y carrito se muestran en la sección completa del catálogo. La separación con el menú es de 24 px en escritorio y móvil. Los textos, bloques con contenido, revisiones y título SEO se conservan; no se modifica la base de datos.

## Actualizar tu instalación existente

1. Descarga **JMAC-ajuste-espacio-catalogo.zip**.
2. En cPanel → Administrador de archivos, entra en **/home/jmaccr**, donde están **jmac_app** y **public_html**. Sube el ZIP y espera a que termine.
3. Selecciona el ZIP → **Extraer / Extract**, destino **/home/jmaccr**. Actualiza los archivos incluidos.
4. En JMAC → **Sistema → Diagnóstico → Vaciar caché**. Después vuelve a Catálogo y pulsa **Ctrl + F5**.

No hay migraciones, instalador ni configuración nueva. Conserva .env, usuarios, MFA, productos, importaciones Excel y solicitudes. El ZIP contiene dos vistas PHP, CSS compilado y sus manifiestos en ambos directorios públicos. JavaScript no cambia ni se incluye. Archivos 0644 y carpetas 0755; no altera los permisos de la raíz privada jmac_app.

El parche se aplica después de la actualización oficial 1.0.0. Conserva una copia de los dos archivos de vistas y del CSS/manifiesto actuales si necesitas revertir únicamente el diseño. No reinstales ni cambies APP_KEY para este ajuste.

## Evidencia

Reproducción con una revisión semilla publicada en base descartable; comprobación de catálogo vacío y con un producto nuevo, cinco anchos (360, 390, 768, 1303 y 1440), buscador y carrito real. Informe y capturas en docs/evidence/catalog-layout-*. No se afirma modificación remota de cPanel: el propietario debe aplicar el ZIP.
