JMAC · {{ $payload['code'] }}
@if($payload['event']==='auth.reset')
Crear o restablecer contraseña: {{ $payload['data']['url'] }}
Enlace de un uso, caduca en 60 minutos. Si no lo solicitaste, ignora el mensaje.
@else
Mensaje de prueba generado por un administrador autorizado.
@endif
