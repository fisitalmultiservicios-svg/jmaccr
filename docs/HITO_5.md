# Hito 5 · solicitudes y notificaciones
Código: servicios Sales/Privacy y Requests, Notifications/MailConfig y Outbox; RequestController, Admin/SalesController, NotificationController, SettingsController; formularios públicos y bandejas reales, requests.js. Migración 000003 añade hashes privados, eventos de contacto, consentimiento y leases de outbox.
Rutas: POST /api/preorders y /api/contacts; /politica-de-privacidad; /admin/preorders, contacts, notifications, settings y acciones protegidas.

Pruebas ejecutadas: php artisan test (SQLite): 53 tests, 366 aserciones, cero fallos. SalesTest: 10 tests. python tests/Browser/cms.py: aprobación legal, carrito, confirmación visible, reintento HTTP misma sesión/mismo consecutivo, seguimiento de ventas y consulta persistida PASSED. python tests/Browser/concurrency_smtp.py: MariaDB, 4 procesos/10 intentos, 7 solicitudes únicas/consecutivos 1–7; cuatro intentos con un token crean solo una fila. Symfony/Laravel SMTP real contra sumidero local aceptó 7 mensajes, outbox sent=7. Prueba SMTP caído conserva solicitud y muestra fallo reintentable. Datos QA nuevos y bases descartables eliminados.

Evidencia real: docs/evidence/h5-*.json y capturas preventa-confirmada/bandeja-ventas. El SMTP local no demuestra recepción externa en cPanel. Abrir WhatsApp no confirma envío. Formularios públicos bloqueados hasta aprobar política propia. Configurar email del equipo, SMTP y Cron finito antes de staging externo.

Probar: crear un producto nuevo, aprobar privacidad propia, añadir al carrito y solicitar. Comprobar referencia en la bandeja y snapshot; cambiar estado con nota. Ejecutar php artisan jmac:outbox --limit=20 y revisar resultados. No ejecutar pruebas sobre BD comercial.
