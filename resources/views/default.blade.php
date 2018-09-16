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
        @if (isset($canonical) && $canonical)
            <link rel="canonical" href="{{ url($canonical) }}" />
        @endif

        {{-- CSS --}}
        <link rel="stylesheet" href="{{ larecipe_assets('css/app.css') }}">
        <script src="{{ larecipe_assets('js/app.js') }}" defer></script>

        {{-- Icon --}}
        <link rel="apple-touch-icon" href="{{ asset(config('larecipe.ui.fav')) }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset(config('larecipe.ui.fav')) }}"/>

        {{-- Custom CSS --}}
        <style>
            .btn-primary, .bg-primary, .badge-primary, .btn-primary:hover, .btn-outline-primary:hover, .btn-outline-primary:active {
                background-color: {{ config('larecipe.ui.colors.primary') }} !important;
                border-color: {{ config('larecipe.ui.colors.primary') }} !important;
            }
            .btn-outline-primary {
                border-color: {{ config('larecipe.ui.colors.primary') }};
            }
            .documentation h1 {
                border-left: 2px solid {{ config('larecipe.ui.colors.primary') }} !important;
            }
            .btn-outline-primary, .documentation .article h2 a:before, .documentation .article :not(pre)>code, a {
                color: {{ config('larecipe.ui.colors.primary') }};
            }
            .documentation .sidebar>ul>li>ul>li.is-active {
                border-left: 2px solid {{ config('larecipe.ui.colors.primary') }};
            }
            .custom-toggle input:checked+.custom-toggle-slider {
                border: 1px solid {{ config('larecipe.ui.colors.primary') }};
            }
            .custom-toggle input:checked + .custom-toggle-slider::before, .alert-primary, .badge-primary {
                background: {{ config('larecipe.ui.colors.primary') }};
            }
            :not(pre)>code[class*=language-], pre[class*=language-] {
                border-top: 3px solid {{ config('larecipe.ui.colors.primary') }};
            }
            .bg-gradient-primary {
                background: linear-gradient(87deg, {{ config('larecipe.ui.colors.primary') }} 0, {{ config('larecipe.ui.colors.secondary') }} 100%) !important;
            }
        </style>
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

            @if(config('larecipe.ui.back_to_top'))
                <larecipe-back-to-top></larecipe-back-to-top>
            @endif
        </div>

        {{-- Custom JS --}}
        @if(!empty(config('larecipe.ui.additional_js')))
            @foreach(config('larecipe.ui.additional_js') as $js)
                <script type="text/javascript" src="{{ asset($js) }}"></script>
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
    </body>
</html>
