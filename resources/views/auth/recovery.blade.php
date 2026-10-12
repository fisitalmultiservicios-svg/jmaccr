@extends('layouts.base')
@section('content')<section class="card narrow"><h1>Segundo factor activado</h1><p>Estos códigos solo se muestran ahora. Guarda cada uno en un lugar privado. Cada código se puede usar una vez.</p><ul>@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul><a class="button" href="/admin">Continuar al panel</a></section>@endsection
