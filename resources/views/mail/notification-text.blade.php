JMAC · {{ $payload['code'] }}
Solicitud registrada en JMAC.
@if(($payload['audience']??'')==='owner')@foreach($payload['customer'] as $field=>$value){{ $field }}: {{ $value }}
@endforeach @endif
@foreach($payload['extra']['lines']??[] as $line){{ $line['sku'] }} · {{ $line['name'] }} · Cantidad {{ $line['quantity'] }}
@endforeach
@foreach(['message','notes','status'] as $field)@if(!empty($payload['extra'][$field])){{ $payload['extra'][$field] }}
@endif @endforeach
Este mensaje no constituye una compra ni confirma disponibilidad.
