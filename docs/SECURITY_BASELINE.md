# Línea base de seguridad
Auth framework, hash bcrypt/Argon2id, rate limits por IP/hash email; respuestas no enumerables. MFA TOTP con secreto cifrado y códigos de recuperación hash de un solo uso; impedir replay del timestep; regenerar sesión tras password/MFA. Desactivación o cambio password/roles revalidado en servidor. Auth+MFA antes de cualquier permiso admin.

CSRF en mutaciones incluidas APIs sesión; CSP sin unsafe-inline, escape Blade, SQL ORM/parametrizado. Media decoder/MIME/límite pixels/bytes, nombres UUID, privado y reencode; no SVG/ZIP/PHP/HTML. TLS obligatorio producción, cookies HttpOnly/Secure/SameSite. Audit redacted, no passwords/keys/PII completa. APP_DEBUG=false producción, logs/env/vendor/backups fuera public.

No datos antiguos, secretos reales, cambios producción ni scripts WP. Pruebas adversariales y dependency audit antes de cada release. MFA/reset/recovery/locks requieren tests reales, no controles decorativos.
