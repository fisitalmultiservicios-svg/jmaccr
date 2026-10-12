@extends('layouts.site')
@section('content')
@php
    $blocks = $document['blocks'];
    if ($page->slug === 'catalogo') {
        $blocks = array_values(array_filter($blocks, static function (array $block): bool {
            // The full catalogue below already includes its categories and cart.
            if ($block['type'] === 'catalog_categories') {
                return false;
            }
            if ($block['type'] === 'cta') {
                $data = $block['data'];
                return filled($data['heading'] ?? null) || filled($data['body'] ?? null) || filled($data['button_url'] ?? null);
            }
            return true;
        }));
    }
@endphp
@if(count($blocks))
@if(($blocks[0]['type']??'')!=='hero')
@if($page->slug==='catalogo')<h1 class="sr-only">{{ $page->title }}</h1>@else<div class="shell section-space"><h1>{{ $page->title }}</h1></div>@endif
@endif
@include('site.blocks',['blocks'=>$blocks])
@else
@if(!in_array($page->slug,['nosotros','contacto','novedades','catalogo']))
<section class="page-heading"><div class="shell"><p class="eyebrow">JMAC · FOR THE INDUSTRY</p><h1>{{ $page->slug==='' ? 'JMAC para la industria' : $page->title }}</h1><p>{{ $page->slug==='' ? 'LIDER EN EL MERCADO INDUSTRIAL' : 'Información corporativa y atención JMAC' }}</p><div class="actions"><a class="button" href="/catalogo/">Ver catálogo</a><a class="button secondary" href="/contacto/">Contáctanos</a></div></div></section>
@else
<h1 class="sr-only">{{ $page->title }}</h1>
@endif
@if(in_array($page->slug,['','nosotros']))<section class="shell section-space"><div class="empty-state"><span class="eyebrow">CONTENIDO EN PREPARACIÓN</span><h2>{{ $page->slug==='' ? 'Bienvenido a JMAC' : 'Conoce JMAC' }}</h2><p>Los contenidos de esta página están pendientes de aprobación.</p><a href="/contacto/">Enviar una consulta</a></div></section>@endif
@endif
@if($page->slug==='catalogo')@include('site.catalog')@endif
@if($page->slug==='novedades' && !in_array('news_feed',array_column($document['blocks'],'type')))@include('site.news')@endif
@if($page->slug==='contacto' && !in_array('contact_form',array_column($document['blocks'],'type')))@include('site.contact')@endif
@endsection
