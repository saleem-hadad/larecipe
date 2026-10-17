@php
    /** @var \LaRecipe\Domains\Content\DTOs\DocSet $set */
    /** @var array $boot */
    /** @var \LaRecipe\Domains\Delivery\Support\Assets $assets */
    $nonce = app(\LaRecipe\LaRecipe::class)->cspNonce();
    $nonceAttribute = $nonce ? ' nonce="'.e($nonce).'"' : '';
@endphp
<!doctype html>
<html lang="en" dir="ltr" class="lr-html lr-admin-html">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>Admin · {{ $set->manifest->name }}</title>
    @if ($set->manifest->favicon())
    <link rel="icon" href="{{ $set->manifest->favicon() }}">
    @endif
    <script{!! $nonceAttribute !!}>(function(){try{var d=document.documentElement,s=localStorage.getItem('larecipe:theme')||'system';var t=s==='system'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):s;d.setAttribute('data-theme',t)}catch(e){}})();</script>
    {{ $assets->tags('admin') }}
</head>
<body class="lr-body-root lr-admin-body">
    <div id="larecipe-admin" data-lr-admin>
        <noscript><p class="lr-admin-noscript">The LaRecipe admin needs JavaScript. The docs themselves work without it.</p></noscript>
    </div>
    <script type="application/json" id="lr-admin-boot">{!! json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</body>
</html>
