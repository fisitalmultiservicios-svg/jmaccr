@extends('layouts.admin')
@section('content')<p class="eyebrow">SITIO WEB</p><h1>Menús</h1><p>Los cambios se guardan y publican por revisión.</p><div class="grid">@foreach($menus as $menu)<a class="card" href="/admin/menus/{{ $menu->id }}"><h2>{{ $menu->name }}</h2><p>Abrir editor</p></a>@endforeach</div>@endsection
