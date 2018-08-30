<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>{{ config('app.name') }}</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="author" content="Binary Torch Sdn. Bhd.">
        <meta name="description" content="Write gorgeous docs for your products using MarkDown inside your Laravel app">
        <meta name="keywords" content="Docs">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (isset($canonical))
            <link rel="canonical" href="{{ url($canonical) }}" />
        @endif
        <link href='https://fonts.googleapis.com/css?family=Miriam+Libre:400,700' rel='stylesheet' type='text/css'>
        <link rel="stylesheet" href="{{ larecipe_assets('css/light.css') }}">
        <script src="{{ larecipe_assets('js/app.js') }}" defer></script>
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/flexboxgrid/6.3.1/flexboxgrid.min.css">
    </head>
    <body>
        <div id="app">
            @include('larecipe::partials.nav')
    
            @yield('content')
        </div>
    </body>
</html>
