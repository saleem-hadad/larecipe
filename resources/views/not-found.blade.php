<!doctype html>
<html lang="{{ $set->defaultLocale()->code }}" dir="{{ $set->defaultLocale()->dir->value }}" data-theme-mode="{{ $set->manifest->theme->mode }}" class="lr-html">
<head>
    @include('larecipe::partials.head', ['title' => 'Page not found · '.$set->manifest->name, 'description' => null, 'canonical' => null, 'markdown' => null, 'noindex' => true, 'themeCss' => app(\LaRecipe\Domains\Delivery\Support\ThemeCss::class)->render($set->manifest->theme)])
</head>
<body class="lr-body-root">
    <main class="lr-not-found" data-lr-not-found>
        <p class="lr-label">404</p>
        <h1 class="lr-title">Page not found</h1>
        <p class="lr-lead">There is no page at <code>/{{ $docs->path }}</code>.</p>
        @if (count($suggestions) > 0)
            <ul class="lr-suggestions">
                @foreach ($suggestions as $suggestion)
                    <li><a href="{{ $suggestion['url'] }}">{{ $suggestion['title'] }}</a>@if ($suggestion['description']) <span>{{ $suggestion['description'] }}</span>@endif</li>
                @endforeach
            </ul>
        @endif
        <p><a href="{{ $home }}">Back to {{ $set->manifest->name }}</a></p>
    </main>
</body>
</html>
