{{-- Mirrors ui/src/regions/Header.tsx element for element. --}}
@php
    $logo = $boot['set']['logo'];
    $versions = $boot['set']['versions'];
    $locales = $boot['set']['locales'];
    $currentVersion = collect($versions)->firstWhere('id', $boot['version']);
    $versionLabel = $currentVersion['label'] ?? $boot['version'];
    $currentLocale = collect($locales)->firstWhere('code', $boot['locale']['code']);
    $themeIcon = ['light' => 'Sun', 'dark' => 'Moon'][$set->manifest->theme->mode] ?? 'Monitor';
    $themePreference = in_array($set->manifest->theme->mode, ['light', 'dark'], true) ? $set->manifest->theme->mode : 'system';
@endphp
<header class="lr-header" data-lr-region="Header">
    <div class="lr-header-start">
        <a class="lr-icon-button lr-menu-button" href="#lr-sidebar" aria-controls="lr-sidebar" aria-expanded="false" aria-label="{{ $t('header.menu') }}">@include('larecipe::partials.icon', ['name' => 'Menu', 'iconSize' => 18, 'iconClass' => ''])</a>
        @include('larecipe::partials.brand')
        @if (count($versions) > 1)
            <details class="lr-menu lr-version-menu"><summary class="lr-version-button" aria-label="{{ $t('header.version') }} {{ $versionLabel }}"><span class="lr-ltr">{{ $versionLabel }}</span>@include('larecipe::partials.icon', ['name' => 'ChevronDown', 'iconSize' => 12, 'iconClass' => ''])</summary><div class="lr-menu-panel"><p class="lr-label lr-menu-label">{{ $t('header.versions') }}</p><ul class="lr-menu-list">@foreach ($versions as $version)<li><a class="lr-menu-item lr-menu-item-mono" href="{{ $version['url'] }}" @if ($version['id'] === $boot['version']) aria-current="true" @endif><span class="lr-ltr">{{ $version['label'] }}</span>@if (in_array($version['status'], ['deprecated', 'archived'], true))<span class="lr-badge" data-tone="warning">{{ $version['status'] }}</span>@endif</a></li>@endforeach</ul></div></details>
        @endif
    </div>
    <div class="lr-header-search">
        <button type="button" class="lr-search-trigger lr-js-only" aria-label="{{ $t('search.label') }}" aria-haspopup="dialog" aria-keyshortcuts="Meta+K Control+K /" data-lr-search-trigger="">@include('larecipe::partials.icon', ['name' => 'Search', 'iconSize' => 15, 'iconClass' => ''])<span class="lr-search-placeholder">{{ $t('search.placeholder') }}</span><kbd class="lr-kbd">⌘K</kbd></button>
    </div>
    <div class="lr-header-end">
        @include('larecipe::prerender.inserts', ['point' => 'header.actions'])
        @include('larecipe::prerender.navbar')
        @if ($askEnabled)
            <button type="button" class="lr-button lr-button-primary lr-ask-button lr-js-only" aria-keyshortcuts="Meta+I Control+I">@include('larecipe::partials.icon', ['name' => 'MessageCircle', 'iconSize' => 15, 'iconClass' => ''])<span class="lr-ask-button-text">{{ $t('ask.button') }}</span></button>
        @endif
        @if (count($locales) > 1)
            <details class="lr-menu lr-locale-menu"><summary class="lr-icon-button" aria-label="{{ $t('header.language') }}: {{ $currentLocale['label'] ?? $boot['locale']['code'] }}">@include('larecipe::partials.icon', ['name' => 'Languages', 'iconSize' => 17, 'iconClass' => ''])</summary><div class="lr-menu-panel" data-align="end"><p class="lr-label lr-menu-label">{{ $t('header.languages') }}</p><ul class="lr-menu-list">@foreach ($locales as $item)<li><a class="lr-menu-item" href="{{ $item['url'] }}" lang="{{ $item['code'] }}" hreflang="{{ $item['code'] }}" dir="{{ $item['dir'] }}" data-lr-reload="" @if ($item['code'] === $boot['locale']['code']) aria-current="true" @endif>{{ $item['label'] }}</a></li>@endforeach</ul></div></details>
        @endif
        @if ($boot['set']['repository'])
            <a class="lr-icon-button lr-repo-link" href="{{ $boot['set']['repository'] }}" aria-label="{{ $t('header.repository') }}" rel="noopener">@include('larecipe::partials.icon', ['name' => 'Github', 'iconSize' => 17, 'iconClass' => ''])</a>
        @endif
        <details class="lr-menu lr-theme-menu lr-js-only"><summary class="lr-icon-button" aria-label="{{ $t('header.theme') }}: {{ $t('header.theme_'.$themePreference) }}">@include('larecipe::partials.icon', ['name' => $themeIcon, 'iconSize' => 17, 'iconClass' => ''])</summary><div class="lr-menu-panel" data-align="end"><p class="lr-label lr-menu-label">{{ $t('header.theme') }}</p><ul class="lr-menu-list">@foreach (['light' => 'Sun', 'dark' => 'Moon', 'system' => 'Monitor'] as $option => $optionIcon)<li><button type="button" class="lr-menu-item" aria-pressed="{{ $option === $themePreference ? 'true' : 'false' }}">@include('larecipe::partials.icon', ['name' => $optionIcon, 'iconSize' => 15, 'iconClass' => '']){{ $t('header.theme_'.$option) }}@if ($option === $themePreference)@include('larecipe::partials.icon', ['name' => 'Check', 'iconSize' => 14, 'iconClass' => 'lr-menu-check'])@endif</button></li>@endforeach</ul></div></details>
    </div>
</header>
