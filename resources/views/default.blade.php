<!doctype html>
<html>
    <head>
        {{-- Meta --}}
        <meta charset="utf-8">
        <title>{{ isset($title) ? $title . ' - ' : null }}{{ config('app.name') }}</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- SEO --}}
        <meta name="author" content="{{ config('larecipe.seo.author') }}">
        <meta name="description" content="{{ config('larecipe.seo.description') }}">
        <meta name="keywords" content="{{ config('larecipe.seo.keywords') }}">
        @if (isset($canonical))
            <link rel="canonical" href="{{ url($canonical) }}" />
        @endif

        {{-- CSS --}}
        <link rel="stylesheet" href="{{ larecipe_assets('css/light.css') }}">
        <script src="{{ larecipe_assets('js/app.js') }}" defer></script>

        {{-- Icon --}}
        <link rel="apple-touch-icon" href="{{ config('larecipe.ui.fav') }}">
        <link rel="shortcut icon" type="image/png" href="{{ config('larecipe.ui.fav') }}"/>

        {{-- Custom CSS --}}
        @if(!empty(config('larecipe.ui.additional_css')))
            @foreach(config('larecipe.ui.additional_css') as $css)
                <link rel="stylesheet" type="text/css" href="{{ asset($css) }}">
            @endforeach
        @endif
    </head>
    <body>
        <div id="app">
            @include('larecipe::partials.nav')
    
            @yield('content')
        </div>

        {{-- Custom JS --}}
        @if(!empty(config('larecipe.ui.additional_js')))
            @foreach(config('larecipe.ui.additional_js') as $js)
                <script type="text/javascript" src="{{ asset($js) }}"></script>
            @endforeach
        @endif
    </body>
</html>
