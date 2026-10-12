# Hito 4 · Catálogo nuevo y carrito

Migración extend_catalog: búsqueda normalizada, SEO/tags/stock privado y product_documents. Catalog/Catalog, TaxonomyController, ProductController, CatalogController, vistas CRUD, fichas, filtros, atributos y JS del carrito. Sin importador histórico. CRUD real con validación de slugs/SKU/ciclos; duplicación en borrador, archivado, papelera reversible; imágenes/PDF aprobados; precio privado excluido de respuestas públicas.

Rutas `/admin/catalog/categories`, brands, products y acciones; `/api/catalog/search`; `/catalogo/{slug}`. Búsqueda accent-insensitive, categorías jerárquicas, marcas y especificaciones; compatibilidad `jmac_cat` desconocido devuelve vacío 200. Carrito persistido en el navegador, altas/cantidades/remoción y contador, sin pagos.

`php artisan test --compact`: 43 pruebas / 307 assertions aprobadas SQLite, incluye 1000 registros nuevos de prueba paginados. Pint y build aprobados. Browser: alta de categoría/marca/producto desde formularios reales, publicación, búsqueda acentuada, ficha/galería y carrito; 5 anchos sin overflow. Capturas h4-producto-admin y h4-catalogo-carrito; h4-browser.json. BD descartable borrada y media generada eliminada; DB desarrollo sigue vacía.

Para probar: crear categoría y marca nuevas visibles, cargar media si se necesita, crear SKU único, guardar borrador, comprobar que no aparece, publicar y buscar. La confirmación comercial se implementa en H5; no se anuncia pedido enviado en este hito. Hosting y aprobación visual siguen pendientes.
