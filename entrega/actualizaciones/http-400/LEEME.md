# Dominio oficial: HTTP 400 y entrada por HTTPS

El 13 de octubre de 2026 el propietario informó DNS hacia el hosting nuevo y «400 Bad Request»; el proveedor lo atribuyó al backend. Se comprobó públicamente que https://jmaccr.com/ y https://www.jmaccr.com/ respondían 200 con JMAC 2.0, mientras http://jmaccr.com/ respondía 400 con la página de error Symfony. Esto reproduce el rechazo explícito de SecurityHeaders: en producción exige HTTPS. No se verificó el cambio DNS por acceso a cPanel ni se atribuye todo posible error del proveedor a esta misma causa.

La corrección redirige únicamente GET y HEAD HTTP a la dirección HTTPS configurada en APP_URL, preservando ruta y query. El destino no utiliza Host, Forwarded ni cabeceras del visitante. APP_URL debe ser una URL HTTPS de origen, sin credenciales, query, fragment ni subdirectorio; una configuración inválida sigue denegada. POST/PUT/PATCH/DELETE HTTP siguen rechazándose antes de ejecutar el backend. No se desactiva HTTPS, SSL, cookies Secure, CSP, permisos, CSRF ni MFA.

## Aplicar la corrección

1. Puedes entrar ahora a **https://jmaccr.com/** y al panel mediante **https://jmaccr.com/admin/login**. Escribe https:// al principio.
2. Descarga **JMAC-correccion-HTTP-400.zip**.
3. En cPanel → Administrador de archivos abre **/home/jmaccr**. Sube y extrae el ZIP en esa misma carpeta, donde están jmac_app y public_html.
4. Se actualiza únicamente **jmac_app/app/Http/Middleware/SecurityHeaders.php** y se añade el manual/checksums. Conserva .env, claves, base de datos, usuarios, storage, medios, recursos públicos, correcciones Excel y MFA. No hay migraciones, reinstalación ni cambios de DNS/cPanel/PHP INI.
5. En JMAC → **Sistema → Diagnóstico → Vaciar caché**. Después pulsa **Ctrl + F5**.
6. Escribe **http://jmaccr.com/** en una ventana privada nueva: debe llevarte a **https://jmaccr.com/**. El navegador puede utilizar su política HSTS y abrir directamente HTTPS; el código de redirección se verifica también mediante cliente HTTP sin HSTS en QA.

Si HTTP todavía responde 400, revisa en el archivo privado **jmac_app/.env** únicamente la línea **APP_URL=https://jmaccr.com**, guarda y vacía caché entrando por HTTPS. No compartas el archivo completo ni cambies APP_KEY, DB_PASSWORD o credenciales. Si HTTPS mismo devuelve 400, esta observación no basta para explicar ese error: indica la URL exacta y pide al proveedor la hora/capa que originó la respuesta y cómo llega HTTPS a PHP.

## Alternativa desde cPanel

Con el certificado SSL funcionando, cPanel → **Dominios / Domains** puede ofrecer **Forzar redirección HTTPS / Force HTTPS Redirect** para jmaccr.com. Activarla hace la redirección antes del backend. Si el proveedor usa un proxy que termina TLS, debe documentar sus IP y cabeceras; no se confía indiscriminadamente en proxies ni se cambia TrustProxies sin evidencia. En las comprobaciones públicas iniciales, el backend sí reconoció HTTPS y envió HSTS.

## Evidencia y límites

Pruebas de destino configurado frente a Host ajeno, GET/HEAD con ruta/query, acciones HTTP denegadas pese a X-Forwarded-Proto falso, configuración inválida y mantenimiento de CSP/nonce/HSTS/privacidad en HTTPS. El paquete se comprueba con unzip nativo y runtime cPanel sin dependencias de desarrollo. La prueba de redirección local no simula un certificado público ni afirma cambios del hosting: las consultas HTTPS públicas iniciales sí mantuvieron la validación TLS del cliente. Evidencia en docs/evidence/https-entry-*. No se ejecutaron mutaciones en la web pública ni se aplicó remotamente el parche.
