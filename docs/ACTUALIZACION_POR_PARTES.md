# JMAC 2.0 — actualización mediante archivos individuales

Corrección de empaquetado: la primera versión guardaba archivos con permisos 0600. La entrega actual especifica 0644 para archivos y 0755 para directorios públicos. Si ya aplicaste la primera versión y la web aparece sin estilos/logo, usa el parche pequeño explicado en [REPARACION_ESTILOS_LOGO.md](REPARACION_ESTILOS_LOGO.md). No vuelvas a extraer una copia antigua del ZIP.

Alternativa de instalación para el propietario, que no dispone de soporte del hosting. El ZIP anterior sigue retirado. No se ha confirmado un falso positivo ni se certifica un resultado antivirus del servidor.

Los archivos proceden de la misma aplicación limpia revisada y probada. El JavaScript se entrega como `.js` normal, sin codificarlo, ocultarlo ni cambiar su extensión. El antivirus del hosting debe permanecer activo y aceptar cada subida; si rechaza cualquier archivo, detener el proceso y conservar el aviso. No se utilizan ZIP cifrados, descargas desde PHP ni scripts que eludan el Administrador de archivos.

## Descargas

1. `JMAC-visual-PHP-CSS-logo.zip`: PHP, plantillas, CSS, manifiestos y logo original; no contiene JavaScript. No incluye datos, `.env`, claves, dependencias, índices públicos, migraciones ni instaladores.
2. `app-B9eZnYus.js`: JavaScript compilado de la aplicación, de 28.466 bytes. SHA256 en el listado de la entrega. Debe subirse directamente, conservando este nombre, a las dos carpetas indicadas abajo.

Antes de subir, en Windows selecciona ambos archivos descargados, pulsa con el botón derecho y elige **Analizar con Microsoft Defender**; en Windows 11 puede estar dentro de **Mostrar más opciones**. Mantén actualizadas las firmas. Si muestra una detección, no continúes y copia el aviso. Este análisis queda a cargo del equipo del propietario; no se afirma realizado desde el cloud.

Si el enlace `.js` muestra texto en el navegador, pulsa Ctrl+S y guarda como `app-B9eZnYus.js`, con tipo **Todos los archivos**, sin añadir `.txt`. El archivo se guarda para subirlo; no hace falta ejecutarlo en Windows.

## Subir desde el Administrador de archivos

Las carpetas `jmac_app` y `public_html` están dentro de `/home/jmaccr`.

1. Abre `/home/jmaccr/public_html/build/assets`. Pulsa **Cargar / Upload** y sube `app-B9eZnYus.js`. Espera a que termine y confirma que el antivirus no lo rechazó. El sitio todavía utiliza su manifiesto anterior y no cambia por subir este archivo nuevo.
2. Abre `/home/jmaccr/jmac_app/public/build/assets`. Sube el **mismo** `app-B9eZnYus.js` y espera a que termine. Si se rechaza cualquiera de estas dos subidas, no extraigas el ZIP.
3. Regresa a `/home/jmaccr`, la carpeta que contiene `jmac_app` y `public_html`. Sube `JMAC-visual-PHP-CSS-logo.zip`. Espera a que la subida sea aceptada por el antivirus.
4. Selecciona el ZIP y pulsa **Extraer / Extract**. Destino: `/home/jmaccr`. Acepta actualizar los archivos incluidos. No crees otra carpeta `public_html` dentro de la actual y no borres las carpetas existentes.
5. Abre JMAC → **Sistema → Diagnóstico → Vaciar caché**. Recarga la web con Ctrl+F5. El archivo hosts de Windows sigue permitiendo probar el hosting nuevo sin cambiar DNS.

No hay que crear otra base de datos, ejecutar instalación, cambiar la contraseña ni volver a programar Cron. La decisión previa sobre MFA se conserva.

La actualización contiene el logo corporativo, carrito gráfico con contador/panel lateral, crédito «Creado por fisitalcr», accesos sencillos del Admin y constructor guiado. El carrito estará vacío hasta que el propietario cree productos nuevos; no se importan productos anteriores ni se publican fotografías o páginas automáticamente.

## Comprobación y recuperación

La prueba de distribución extrae la instalación anterior en un directorio temporal, añade el parche MFA que el dueño ya utiliza, copia el JavaScript a ambos lugares y aplica el ZIP. Comprueba que cada entrada coincide con la fuente revisada, que cada manifiesto referencia archivos presentes y que `.env`, storage e índices públicos se conservan. Se ejecuta la prueba de navegador contra esa copia y su `public_html`, con una MariaDB descartable; nunca contra los datos del dueño.

El código fuente del frontend se conserva en `fuente-frontend/` de esta entrega para futuras compilaciones; no hace falta subirlo ni ejecutar Node en cPanel. La entrega es de recursos compilados para producción.

Para volver a la presentación anterior, restaura únicamente los PHP/Blade/CSS/manifiestos enumerados en el listado de la actualización desde tu copia anterior y vacía las vistas. El archivo JavaScript nuevo puede permanecer sin referencias. No cambies `.env`, datos ni el parche MFA. Las pruebas locales no certifican que el antivirus del hosting acepte las subidas; esa comprobación continúa siendo necesaria.
