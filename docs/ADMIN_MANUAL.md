# Manual JMAC 2.0
## Acceso
/admin/login; cuenta nueva explícita, autenticador MFA y ocho códigos privados de recuperación. MFA para permisos de edición/datos personales. Roles/estado revocan sesiones; recuperación usa outbox/SMTP/Cron.
## Páginas
El inicio ofrece «Editar mi web» y accesos directos. Dentro de una página pulsa la sección en la vista previa o la lista; solo se muestran los campos que utiliza. Estilos y visibilidad están en Opciones avanzadas. SEO, versiones y secciones reutilizables están en paneles desplegables. Guarda antes de publicar; la interfaz señala cambios pendientes.
Selecciona bloque y edita título/texto/imagen/botones/estilo/visibilidad. Paleta de 21 tipos. Arrastra/flechas/duplica/elimina/deshacer/rehacer. Columnas hasta cuatro; repetidores slider/galería/FAQ/características; texto enriquecido seguro.
Revisa desktop/tablet/móvil. Guardar crea revisión sin cambiar público. Publicar revisión guardada exige imágenes aprobadas. Programar necesita Cron. Restaurar crea borrador; publicar para aplicar. Cinco rutas corporativas conservadas. Secciones reutilizables guardables/insertables.
## Diseño y menús
«Logo, colores y pie» incluye el logo oficial y un crédito editable del creador («Creado por fisitalcr» por defecto), con muestra inmediata de identidad y publicación explícita del diseño guardado. El carrito gráfico permite revisar la lista de preventa desde cualquier página.
Colores/fuente/tamaños/ancho/bordes/logo/cabecera fija/pie: guardar borrador, publicar y rollback. Menús: etiqueta/URL interna o HTTPS/orden/visibilidad/publicación/revisión.
## Medios
Importa candidatos o sube nuevos. ALT/derechos/aprobación del dueño. Raster reprocesado/miniatura y PDF reconstruido para fichas. SVG/ZIP/PHP rechazados. No se borran medios referenciados por revisiones/productos/diseño.
## Catálogo
Categorías/marcas nuevas: nombres/rutas/orden/imágenes; padre sin ciclos. Producto: SKU único, descripción/galería/PDF/especificaciones/etiquetas/relaciones/SEO/estado. Borrador privado; publicar valida. Duplicar sin SKU; archivar retira; papelera reversible. Precio/stock privados. No importación WP.
## Solicitudes
Bandejas paginadas/filtros referencia/estado/fecha/teléfono exacto. Consecutivo/datos protegidos/snapshots/responsable/estado/notas/eventos. CSV privado neutraliza fórmulas. Anonimización confirmada preserva histórico técnico. WhatsApp abre chat, no entrega. Web confirma solo después de commit en BD.
## Novedades y SEO
Artículo nuevo, mismo constructor, resumen/categorías/etiquetas/imagen/SEO; guardar/publicar/programar/retirar/rollback. SEO por entidad/global; redirects internos sin ciclos. Sitemap solo publicado en producción, staging noindex.
## Sistema
Invitar usuarios nuevos, roles/permisos server-side. Último superadmin activo protegido, rol reservado inmutable. Reset MFA exige contraseña/confirmación de identidad. SMTP cifrado, contraseña no mostrada; prueba hacia tu buzón y revisar outbox/recepción. Diagnóstico/heartbeat/Cron/proceso finito/caché según permisos. Auditoría filtrada sin secretos. Copias: BACKUP_RESTORE.md.
## Contenido pendiente
Historia/misión/contacto/legal desconocidos permanecen vacíos/editables. Semilla DRAFT/fotos candidatas, no publicación automática ni réplica exacta verificada. HTML local revisa diseño/flujos, no autentica/envía a ventas; PHP/MySQL en ZIP aplica seguridad/persistencia reales.
