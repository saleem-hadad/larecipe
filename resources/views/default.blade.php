<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>{{ isset($title) ? $title . ' - ' : null }}Docs</title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="author" content="Binary Torch">
        <meta name="description" content="Binary Torch">
        <meta name="keywords" content="Binary Torch">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (isset($canonical))
            <link rel="canonical" href="{{ url($canonical) }}" />
        @endif
        <link href='https://fonts.googleapis.com/css?family=Miriam+Libre:400,700' rel='stylesheet' type='text/css'>
        <link rel="stylesheet" href="{{ larecipe_assets('css/app.css') }}">
        <script src="{{ larecipe_assets('js/app.js') }}" defer></script>
        <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/flexboxgrid/6.3.1/flexboxgrid.min.css">
    </head>
    <body class="@yield('body-class', 'docs') language-php">
        <nav class="main">
            <a href="/" class="brand nav-block">
                <span>{{ config('app.name') }}</span>
            </a>

            <div class="search nav-block invisible"></div>

            @if (Request::is('docs*') && isset($currentVersion))
                @include('larecipe::partials.switcher')
            @endif

            <div class="responsive-sidebar-nav">
                <a href="#" class="toggle-slide menu-link btn">&#9776;</a>
            </div>
        </nav>

        @yield('content')

    </body>
</html>
