@extends('layouts.admin')
@section('content')
<p class="eyebrow">BIENVENIDO A JMAC 2.0</p><h1>Tu sitio, paso a paso</h1><p>Elige qué quieres cambiar. Puedes revisar cada cambio antes de publicarlo.</p>
<div class="quick-links">
@if(auth()->user()->hasPermission('pages.view'))<a class="quick-link" href="/admin/pages"><span class="quick-icon" aria-hidden="true">✎</span><div><h2>Editar mi web</h2><p>Textos, imágenes y secciones de cada página.</p></div></a>@endif
@if(auth()->user()->hasPermission('theme.view'))<a class="quick-link" href="/admin/theme"><span class="quick-icon" aria-hidden="true">◈</span><div><h2>Logo y colores</h2><p>Identidad, letra y pie de página.</p></div></a>@endif
@if(auth()->user()->hasPermission('products.view'))<a class="quick-link" href="/admin/catalog/products"><span class="quick-icon"><x-cart-icon /></span><div><h2>Mis productos</h2><p>Crea y organiza tu catálogo nuevo.</p></div></a>@endif
@if(auth()->user()->hasPermission('preorders.view'))<a class="quick-link" href="/admin/preorders"><span class="quick-icon" aria-hidden="true">↗</span><div><h2>Solicitudes recibidas</h2><p>Revisa y atiende las preventas.</p></div></a>@endif
</div>
<div class="card"><h2>Cómo cambiar una página</h2><div class="editing-steps"><span><b>1</b> Abre «Editar mi web»</span><span><b>2</b> Pulsa la sección que quieras cambiar</span><span><b>3</b> Guarda, revisa y publica</span></div><p class="editor-help">Las fotografías corporativas deben aprobarse en Medios antes de publicarlas.</p>@if(auth()->user()->hasPermission('media.view'))<a class="button secondary" href="/admin/media">Mis fotos y archivos</a>@endif<a class="button secondary" href="/" target="_blank" rel="noopener">Ver mi web</a></div>
<h2>Mi catálogo y novedades</h2><div class="grid">@foreach(['categories'=>'Categorías','brands'=>'Marcas','products'=>'Productos','posts'=>'Novedades'] as $key=>$label)<article class="card"><h2>{{ $label }}</h2><strong class="metric">{{ $counts[$key] }}</strong><p>Registros actuales</p></article>@endforeach</div>
@endsection
