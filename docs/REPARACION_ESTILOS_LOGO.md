# Reparar estilos y logo después de la actualización visual

La captura del propietario muestra HTML con el crédito nuevo, sin CSS y con el logo roto. Se encontró un defecto real en el empaquetado: el ZIP visual por partes guardaba los archivos con permisos Unix 0600, y la prueba de extracción con Python no restauraba esos permisos. En hosting compartido, PHP puede leer las plantillas como propietario mientras el servidor de archivos estáticos no puede leer CSS/PNG. Falta comprobar el código HTTP y las rutas del hosting para confirmar que esta es su única causa.

Se corrige el empaquetador: archivos 0644, directorios 0755 explícitos, sin alterar la raíz privada jmac_app. La prueba usa ahora unzip nativo y comprueba lectura pública de CSS/logo, además de conservar permisos/contenido de .env y storage. Se comprobó también que unzip conserva los permisos de directorios ya existentes: una carpeta pública que estuviera en 0700 necesita el cambio manual descrito abajo. No requiere cambio de código PHP, datos, migraciones ni JavaScript.

## Aplicar el parche pequeño

1. Descarga `JMAC-reparacion-estilos-logo.zip`.
2. En el Administrador de archivos abre `/home/jmaccr`, donde están las carpetas `jmac_app` y `public_html`.
3. Sube el ZIP y espera a que el antivirus acepte la subida. Si lo rechaza, conserva el aviso y no continúes.
4. Selecciona el ZIP → **Extraer / Extract**. Destino: `/home/jmaccr`. Actualiza los archivos existentes. El parche contiene CSS, PNG y manifiestos en los dos directorios públicos; no contiene JavaScript, .env, datos, instaladores ni índices PHP.
5. Recarga JMAC con **Ctrl+F5**. Para estos archivos estáticos no hace falta reinstalar, ejecutar Cron ni cambiar la base de datos.

En el mismo navegador que usa el archivo hosts de Windows, abre:

- `https://jmaccr.com/brand/jmac-logo.png`: debe mostrar el logo real.
- `https://jmaccr.com/build/assets/app-O1-jpzL0.css`: debe mostrar texto CSS, sin 403/404.

## Si cPanel conserva los permisos anteriores

En `/home/jmaccr/public_html/build/assets`, selecciona `app-O1-jpzL0.css` → **Permissions / Permisos**. Marca **Read / Leer** en Usuario, Grupo y Otros, y **Write / Escribir** solamente en Usuario. Deja Execute / Ejecutar sin marcar. El número resultante debe ser **644**. Repite con `app-B9eZnYus.js`, si existe.

En `/home/jmaccr/public_html/brand`, aplica los mismos permisos **644** a `jmac-logo.png`. Los directorios públicos `brand`, `build` y `build/assets` necesitan **755**: Leer/Ejecutar para Usuario, Grupo y Otros; Escribir solo para Usuario. No aplicar estos cambios recursivamente a la aplicación ni a .env/storage; no usar 777.

Si aparece 404, verifica que los archivos están dentro de public_html y que no se creó `public_html/public_html`. Si el dominio utiliza otro document root, hay que identificarlo desde cPanel antes de mover archivos. Si los recursos abren pero el diseño no aparece, revisar las URLs de CSS y errores del navegador; no asumir una reparación remota ya confirmada.

Las pruebas locales certifican el contenido y los permisos del parche, no su aceptación antivirus ni su aplicación en el hosting del propietario.
