{{-- Mirrors ui/src/regions/PageHeader.tsx. --}}
@php
    $current = $boot['page'];
    $crumbs = $current['breadcrumb'] ?? [];
    $ticket = $current['ticket'] ?? null;
    $provenanceName = $current['provenance']['name'] ?? null;
    $parts = [];

    if (! empty($current['updated_at']) && ! empty($current['updated_human'])) {
        $time = '<time datetime="'.e($current['updated_at']).'">'.e($current['updated_human']).'</time>';
        $template = e($provenanceName ? $t('page.updated_by') : $t('page.updated'));
        $parts[] = '<span>'.str_replace([':time', ':name'], [$time, '<bdi>'.e((string) $provenanceName).'</bdi>'], $template).'</span>';
    }

    if (! empty($ticket['ref'])) {
        $parts[] = '<span class="lr-hash">'.e($ticket['ref']).'</span>';
    }

    if (! empty($current['reading_label'])) {
        $parts[] = '<span>'.e($current['reading_label']).'</span>';
    }

    $tocItems = array_values(array_filter($current['toc'], fn ($item) => $item['level'] <= 3));
@endphp
<div class="lr-page-header" data-lr-region="PageHeader">
    @if ($current['fallback_from_locale'])
        <p class="lr-fallback-notice" role="note">@include('larecipe::partials.icon', ['name' => 'Languages', 'iconSize' => 16, 'iconClass' => '']){{ $t('page.fallback') }}</p>
    @endif
    @if (count($crumbs) > 0)
        <p class="lr-breadcrumb lr-label">@foreach ([...$crumbs, $current['title']] as $index => $crumb)@if ($index > 0)<span class="lr-breadcrumb-sep" aria-hidden="true">/</span>@endif<span>{{ $crumb }}</span>@endforeach</p>
    @endif
    <h1 class="lr-title">{{ $current['title'] }}</h1>
    @if ($current['description'])
        <p class="lr-lead">{{ $current['description'] }}</p>
    @endif
    @if (count($parts) > 0)
        <p class="lr-provenance">@if ($current['fresh'] && $current['updated_at'])<span class="lr-fresh-dot" aria-hidden="true"></span>@endif{!! implode('<span class="lr-sep" aria-hidden="true">·</span>', $parts) !!}</p>
    @endif
    <div class="lr-page-tools">
        @include('larecipe::prerender.page-actions', ['variant' => 'split'])
        @if (count($tocItems) > 0)
            <details class="lr-menu lr-toc-disclosure"><summary class="lr-button lr-button-outline lr-button-sm">@include('larecipe::partials.icon', ['name' => 'TextAlignStart', 'iconSize' => 15, 'iconClass' => '']){{ $t('page.on_this_page') }}</summary><div class="lr-menu-panel"><ul class="lr-menu-list">@foreach ($tocItems as $item)<li class="lr-toc-level-{{ $item['level'] }}"><a class="lr-menu-item" href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>@endforeach</ul></div></details>
        @endif
    </div>
</div>
