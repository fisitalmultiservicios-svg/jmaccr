# ADR 004 — Excel administrativo sobre cPanel PHP 8.3

Decisión: OpenSpout 4.32.0, lectura XLSX en streaming y escritura de una plantilla sin datos comerciales. Se fijó la versión en composer.lock, manteniendo platform.php 8.3.0. OpenSpout 5 requiere PHP 8.4+, incompatible con la versión del hosting informada por el dueño. No se usa WordPress ni un importador del catálogo anterior.

La descarga dist de GitHub API recibió HTTP 403; Composer instaló mediante su ruta de Git HTTPS soportada, con la fuente oficial y commit `41f045c1f632e1474e15d4c7bc3abcb4a153563d` registrado en el lock. No se desactivaron TLS ni verificaciones. composer audit no reportó vulnerabilidades ni paquetes abandonados.

Preflight limita ZIP/partes/XML/dimensiones y rechaza contenido activo antes de usar el lector. Las filas se validan en servidor; preview no modifica catálogo. El payload normalizado se cifra en storage privado. Se reutilizan import_batches/import_errors ya presentes, sin migraciones nuevas ni cambios en datos iniciales.

La confirmación usa un lock de proceso finito y transacción con bloqueo de filas/fingerprint del catálogo. El batch completado hace los reintentos idempotentes. Reemplazo es retirada reversible de ausentes, con snapshots de ventas intactos. Se crea copia cifrada usando el servicio de backups existente antes de escribir; restaura completa solo en staging según la política actual.

Se conserva el código de imágenes y sus aprobaciones: Excel no descarga ni publica medios. La importación procede exclusivamente de archivos nuevos elegidos por el administrador; no se crea ninguna tarea que lea las fuentes históricas ni se añaden datos a las semillas.

Requisitos adicionales: DOM, XMLReader y ZIP habilitados en PHP. Dependencia y autoload de producción incluidos en la entrega, sin instalación de Composer en cPanel. Límites de memoria y upload del hosting deben permitir el archivo; se recomienda memory_limit 256M, upload_max_filesize 8M y post_max_size 12M, sujetos al plan real.
