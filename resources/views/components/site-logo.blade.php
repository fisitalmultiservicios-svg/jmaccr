@props(['site' => null, 'class' => 'site-logo'])
@php($logoId = $site['tokens']['logo_media_id'] ?? null)
@php($logoUrl = $logoId ? (($site['preview'] ?? false) ? '/admin/media/'.$logoId.'/preview' : '/media/'.$logoId) : '/brand/jmac-logo.png')
<img class="{{ $class }}" src="{{ $logoUrl }}" alt="J-MAC · FOR THE INDUSTRY" width="679" height="523">
