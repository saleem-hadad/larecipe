@php
    /** @var \LaRecipe\Domains\Content\DTOs\DocSet $set */
    /** @var \LaRecipe\Domains\Content\DTOs\Page $page */
    /** @var \LaRecipe\Domains\Markdown\DTOs\RenderedPage $rendered */
    /** @var array $boot */
    $locale = $boot['locale'];
    $themeCss = app(\LaRecipe\Domains\Delivery\Support\ThemeCss::class)->render($set->manifest->theme);
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $page->title,
        'description' => $page->description,
        'url' => $boot['page']['url'],
        'dateModified' => $boot['page']['updated_at'],
        'isPartOf' => ['@type' => 'WebSite', 'name' => $set->manifest->name, 'url' => $boot['urls']['base']],
    ];

    // UI strings in the page's locale: the same table React receives, so no word
    // changes when React takes over.
    $strings = $boot['strings'] ?? [];
    $t = function (string $key, array $replace = []) use ($strings): string {
        $line = $strings[$key] ?? $key;
        uksort($replace, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($replace as $name => $value) {
            $line = str_replace(':'.$name, (string) $value, $line);
        }

        return $line;
    };
    $askEnabled = ! empty($boot['features']['ask']) && ! empty($boot['ask']['endpoint']) && ($boot['ask']['enabled'] ?? true) !== false;
    $feedbackEnabled = ! empty($boot['features']['feedback']) && ! empty($boot['feedback']['endpoint']);
    $themeMode = $set->manifest->theme->mode;
@endphp
<!doctype html>
<html lang="{{ $locale['code'] }}" dir="{{ $locale['dir'] }}" data-theme-mode="{{ $themeMode }}" @if (in_array($themeMode, ['light', 'dark'], true)) data-theme="{{ $themeMode }}" @endif class="lr-html">
<head>
    @include('larecipe::partials.head', ['title' => $page->title.' · '.$set->manifest->name, 'description' => $page->description, 'canonical' => $boot['page']['url'], 'markdown' => $boot['page']['markdown_url'], 'noindex' => $boot['page']['noindex'], 'themeCss' => $themeCss, 'version' => $page->ref->version])
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @foreach ($boot['page']['alternates'] ?? [] as $alternate)
        <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['url'] }}">
    @endforeach
</head>
<body class="lr-body-root">
    <a class="lr-skip" href="#lr-article">{{ $t('skip') }}</a>
    @if (! empty($boot['urls']['llms']))
        {{-- For agents that read the HTML: the Markdown index. Out of the tab order; people use the For agents menu. --}}
        <a class="lr-sr" href="{{ $boot['urls']['llms'] }}" tabindex="-1">{{ $t('agents_index') }}</a>
    @endif
    <div id="larecipe" class="lr-root" data-lr-root data-layout="{{ $boot['page']['layout'] }}">
        @include('larecipe::prerender.header')
        <div class="lr-shell">
            @include('larecipe::prerender.sidebar')
            <main class="lr-main" id="lr-main">
                @include('larecipe::prerender.page-header')
                @include('larecipe::prerender.inserts', ['point' => 'article.before'])
                <div class="lr-article-host"><article class="lr-article lr-prose" id="lr-article" data-lr-article>{!! $rendered->html !!}</article></div>
                @include('larecipe::prerender.inserts', ['point' => 'article.after'])
                @include('larecipe::prerender.page-footer')
                @if ($askEnabled)
                    @include('larecipe::prerender.ask-bar')
                @endif
            </main>
            @include('larecipe::prerender.rail')
        </div>
        @include('larecipe::prerender.footer')
    </div>
    <script type="application/json" id="lr-boot">{!! json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</body>
</html>
