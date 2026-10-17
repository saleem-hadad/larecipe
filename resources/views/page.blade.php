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
@endphp
<!doctype html>
<html lang="{{ $locale['code'] }}" dir="{{ $locale['dir'] }}" data-theme-mode="{{ $set->manifest->theme->mode }}" class="lr-html">
<head>
    @include('larecipe::partials.head', ['title' => $page->title.' · '.$set->manifest->name, 'description' => $page->description, 'canonical' => $boot['page']['url'], 'markdown' => $boot['page']['markdown_url'], 'noindex' => $boot['page']['noindex'], 'themeCss' => $themeCss])
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @foreach ($boot['set']['locales'] as $alternate)
        <link rel="alternate" hreflang="{{ $alternate['code'] }}" href="{{ url($alternate['url']) }}">
    @endforeach
</head>
<body class="lr-body-root">
    <a class="lr-skip" href="#lr-article">Skip to content</a>
    <div id="larecipe" class="lr-root" data-lr-root data-layout="{{ $boot['page']['layout'] }}">
        @include('larecipe::prerender.header', ['boot' => $boot])
        <div class="lr-shell">
            @include('larecipe::prerender.sidebar', ['boot' => $boot])
            <main class="lr-main" id="lr-main">
                @include('larecipe::prerender.page-header', ['boot' => $boot])
                @include('larecipe::prerender.inserts', ['point' => 'article.before', 'boot' => $boot])
                <article class="lr-article lr-prose" id="lr-article" data-lr-article>{!! $rendered->html !!}</article>
                @include('larecipe::prerender.inserts', ['point' => 'article.after', 'boot' => $boot])
                @include('larecipe::prerender.page-footer', ['boot' => $boot])
            </main>
            @include('larecipe::prerender.rail', ['boot' => $boot])
        </div>
    </div>
    <script type="application/json" id="lr-boot">{!! json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</body>
</html>
