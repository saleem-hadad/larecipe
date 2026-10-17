@php
    /** @var \LaRecipe\Domains\Content\DTOs\DocSet $set */
    /** @var \LaRecipe\Domains\Delivery\DTOs\DocsRequest $docs */
    $locale = $set->locale($docs->locale) ?? $set->defaultLocale();
    $code = $locale->code;
    $dir = $locale->dir->value;
    $tr = fn (string $key, array $replace = []) => __('larecipe::ui.'.$key, $replace, $code);
    $themeMode = $set->manifest->theme->mode;
    // The brand partial reads the same keys as the page's boot payload.
    $boot = ['urls' => ['base' => $home], 'set' => ['logo' => $set->manifest->logo(), 'name' => $set->manifest->name]];
    $requested = '/'.ltrim(request()->path(), '/');
    $base = rtrim($set->config->basePath(), '/');
@endphp
<!doctype html>
<html lang="{{ $code }}" dir="{{ $dir }}" data-theme-mode="{{ $themeMode }}" @if (in_array($themeMode, ['light', 'dark'], true)) data-theme="{{ $themeMode }}" @endif class="lr-html">
<head>
    @include('larecipe::partials.head', ['title' => $tr('not_found.title').' · '.$set->manifest->name, 'description' => null, 'canonical' => null, 'markdown' => null, 'noindex' => true, 'themeCss' => app(\LaRecipe\Domains\Delivery\Support\ThemeCss::class)->render($set->manifest->theme)])
</head>
<body class="lr-body-root">
    <div class="lr-not-found-page">
        <header class="lr-header">
            <div class="lr-header-start">@include('larecipe::partials.brand')</div>
        </header>
        <main class="lr-not-found" data-lr-not-found>
            <p class="lr-label lr-not-found-code">{{ $tr('not_found.label') }}</p>
            <h1 class="lr-not-found-title">{{ $tr('not_found.title') }}</h1>
            <p class="lr-not-found-body">{!! str_replace(':path', '<code>'.e($requested).'</code>', e($tr('not_found.body'))) !!}</p>
            @if (count($suggestions) > 0)
                <div class="lr-perf" aria-hidden="true"></div>
                <p class="lr-label">{{ $tr('not_found.suggestions') }}</p>
                <ul class="lr-suggestions">
                    @foreach ($suggestions as $suggestion)
                        @php($path = (string) (parse_url($suggestion['url'], PHP_URL_PATH) ?? $suggestion['url']))
                        <li><a class="lr-suggestion" href="{{ $suggestion['url'] }}"><span class="lr-suggestion-title">{{ $suggestion['title'] }}</span><span class="lr-suggestion-path">{{ $base !== '' && str_starts_with($path, $base) ? (substr($path, strlen($base)) ?: '/') : $path }}</span>@if (! empty($suggestion['description']))<span class="lr-suggestion-description">{{ $suggestion['description'] }}</span>@endif</a></li>
                    @endforeach
                </ul>
            @else
                <div class="lr-perf" aria-hidden="true"></div>
            @endif
            <a class="lr-button lr-button-outline lr-not-found-home" href="{{ $home }}">@include('larecipe::partials.icon', ['name' => 'ArrowLeft', 'iconSize' => 15, 'iconClass' => '']){{ $tr('not_found.home', ['name' => $set->manifest->name]) }}</a>
        </main>
    </div>
</body>
</html>
