<!doctype html>
<html>
    <head>
        {{-- Meta --}}
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>{{ isset($title) ? $title . ' | ' : null }}{{ config('app.name') }}</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- SEO --}}
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

        {{-- CSS --}}
        <link rel="stylesheet" href="{{ larecipe_assets('css/light.css') }}">
        <link rel="stylesheet" href="{{ larecipe_assets('css/dark.css') }}">

        {{-- Icon --}}
        <link rel="apple-touch-icon" href="{{ asset(config('larecipe.ui.fav')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset(config('larecipe.ui.fav')) }}"/>
        
        {{-- Dynamic color --}}
        @include('larecipe::partials.style')

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @foreach(LaRecipe::allStyles() as $name => $path)
            <link rel="stylesheet" href="{{ $path }}">
        @endforeach
    </head>
    <body>
        <div id="app" v-cloak>
            @include('larecipe::partials.nav')
            
            <!-- @include('larecipe::partials.plugins.search') -->
            
            @yield('content')

            @if(config('larecipe.ui.back_to_top'))
                <larecipe-back-to-top></larecipe-back-to-top>
            @endif
        </div>


        <script>
            window.config = @json([]);
        </script>

        <script type="text/javascript">
            if(localStorage.getItem('larecipeSidebar') == null) {
                localStorage.setItem('larecipeSidebar', !! {{ config('larecipe.ui.show_side_bar') ?: 0 }});
            }
        </script>

        <script src="{{ larecipe_assets('js/app.js') }}"></script>

        <script>
            window.Larecipe = new CreateLarecipe(config)
        </script>

        {{-- Custom JS --}}
        @if(!empty(config('larecipe.ui.additional_js')))
            @foreach(config('larecipe.ui.additional_js') as $js)
                <script type="text/javascript" src="{{ asset($js) . '?id=' . \Illuminate\Support\Str::random(8) }}"></script>
            @endforeach
        @endif

        {{-- Google Analytics --}}
        @if(config('larecipe.settings.ga_id'))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('larecipe.settings.ga_id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', "{{ config('larecipe.settings.ga_id') }}");
            </script>
        @endif
        {{-- /Google Analytics --}}

        @foreach (LaRecipe::allScripts() as $name => $path)
            <script src="{!! $path !!}"></script>
        @endforeach

        <script>
            Larecipe.run()
        </script>
    </body>
</html>
