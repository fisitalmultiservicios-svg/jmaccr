# Hito 2 · Sitio público

Añadidos Site/Site.php, SiteController, layouts/site y site/{page,blocks,catalog,news,contact}, CSS responsive, navegación móvil y nonce CSP para estilos del CMS. Sin migraciones nuevas. Rutas reales `/`, `/nosotros/`, `/contacto/`, `/novedades/`, `/catalogo/`, `/media/{id}`; esta última solo sirve media aprobada.

`php artisan test --compact`: 27 pruebas / 187 assertions aprobadas (SQLite); `npm run build` y Pint aprobados. Navegador: 25 combinaciones de rutas/anchos (360,390,768,1024,1440), menú móvil y overflow en docs/evidence/h2-browser.json y capturas h2-*.

Los contenidos semilla siguen en DRAFT y fotos candidatas privadas. Vacíos corporativos funcionan; formulario comercial, CMS, catálogo y noticias se desarrollan en los siguientes hitos. Para probar: abrir las cinco rutas; no habrá productos, datos de contacto ni textos desconocidos. La propuesta visual con contenidos recuperados se verá en la vista previa protegida y HTML de revisión, hasta aprobación.
