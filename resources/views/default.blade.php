<!doctype html>
<html>
    <head>
        <!-- META Tags -->
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>{{ isset($title) ? $title . ' | ' : null }}{{ config('app.name') }}</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- SEO -->
        <meta name="author" content="{{ config('larecipe.seo.author') }}">
        <meta name="description" content="{{ config('larecipe.seo.description') }}">
        <meta name="keywords" content="{{ config('larecipe.seo.keywords') }}">
        <meta name="twitter:card" value="summary">
        @if (isset($canonical) && $canonical)
            <link rel="canonical" href="{{ url($canonical) }}" />
        @endif
        @if($openGraph = config('larecipe.seo.og'))
            @foreach($openGraph as $key => $value)
                @if($value)
                    <meta property="og:{{ $key }}" content="{{ $value }}" />
                @endif
            @endforeach
        @endif

        <!-- CSS -->
        <link rel="stylesheet" href="{{ larecipe_assets('css/app.css') }}">

        <!-- Favicon -->
        @if (config('larecipe.branding.favicon'))
            <link rel="apple-touch-icon" href="{{ asset(config('larecipe.branding.favicon')) }}">
            <link rel="shortcut icon" type="image/png" href="{{ asset(config('larecipe.branding.favicon')) }}"/>
        @endif

        <!-- Dynamic Colors -->
        @include('larecipe::style')

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @foreach(LaRecipe::allStyles() as $name => $path)
            @if (preg_match('/^https?:\/\//', $path))
                <link rel="stylesheet" href="{{ $path }}">
            @else
                <link rel="stylesheet" href="{{ route('larecipe.styles', $name) }}">
            @endif
        @endforeach
        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            } else {
                document.documentElement.classList.remove('dark')
            }
        </script>

    </head>
    <body>
        <div id="app" v-cloak>
            @include('larecipe::partials.nav')

            @yield('content')

            <larecipe-back-to-top></larecipe-back-to-top>
        </div>

        <script src="{{ larecipe_assets('js/app.js') }}"></script>

        <script>
            window.LaRecipe = new CreateLarecipe()
        </script>

        @foreach (LaRecipe::allScripts() as $name => $path)
            @if (preg_match('/^https?:\/\//', $path))
                <script src="{{ $path }}"></script>
            @else
                <script src="{{ route('larecipe.scripts', $name) }}"></script>
            @endif
        @endforeach

        <script>
            LaRecipe.run()

            document.getElementById('switchTheme').addEventListener('click', function() {
                let htmlClasses = document.querySelector('html').classList;
                if(localStorage.theme == 'dark') {
                    htmlClasses.remove('dark');
                    localStorage.removeItem('theme')
                } else {
                    htmlClasses.add('dark');
                    localStorage.theme = 'dark';
                }
            });
        </script>
    </body>
</html>
