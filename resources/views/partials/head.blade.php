@php($larecipe = app(\LaRecipe\LaRecipe::class))
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
@if (! empty($description))
<meta name="description" content="{{ $description }}">
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:title" content="{{ $title }}">
<meta property="og:type" content="article">
@if (! empty($canonical))
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:url" content="{{ $canonical }}">
@endif
@if (! empty($markdown))
<link rel="alternate" type="text/markdown" href="{{ $markdown }}">
@endif
@if (! empty($noindex))
<meta name="robots" content="noindex">
@endif
@if ($set->manifest->favicon())
<link rel="icon" href="{{ $set->manifest->favicon() }}">
@endif
<meta name="color-scheme" content="light dark">
<script{!! $larecipe->cspNonce() ? ' nonce="'.e($larecipe->cspNonce()).'"' : '' !!}>(function(){try{var d=document.documentElement,m=d.getAttribute('data-theme-mode')||'system',s=localStorage.getItem('larecipe:theme');var t=s||m;if(t==='system'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}d.setAttribute('data-theme',t)}catch(e){}})();</script>
@if (! empty($themeCss))
<style>{!! $themeCss !!}</style>
@endif
{{ $assets->tags('app') }}
@foreach ($larecipe->headCallbacks() as $callback)
{!! (function ($value) { return is_object($value) && method_exists($value, 'render') ? $value->render() : (string) $value; })($callback($set)) !!}
@endforeach
