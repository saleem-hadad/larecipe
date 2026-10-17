{{-- Mirrors ui/src/regions/Sidebar.tsx. On phones the sheet opens through :target when JavaScript is off. --}}
@php
    $sidebarExtras = count($boot['set']['navbar']['links'] ?? []) > 0 || ! empty($boot['set']['navbar']['cta']) || count($boot['set']['locales']) > 1;
@endphp
<nav class="lr-sidebar" id="lr-sidebar" aria-label="{{ $t('nav.label') }}" data-lr-region="Sidebar">
    <div class="lr-sidebar-head">@include('larecipe::partials.brand')<a class="lr-icon-button" href="#" aria-label="{{ $t('header.close_menu') }}">@include('larecipe::partials.icon', ['name' => 'X', 'iconSize' => 18, 'iconClass' => ''])</a></div>
    @include('larecipe::prerender.inserts', ['point' => 'sidebar.before'])
    @foreach ($boot['navigation'] as $tab)
        <div class="lr-nav-tab-group">@if ($tab['label'])<div class="lr-nav-tab">{{ $tab['label'] }}</div>@endif @include('larecipe::prerender.nav-items', ['items' => $tab['items'], 'depth' => 0])</div>
    @endforeach
    @include('larecipe::prerender.inserts', ['point' => 'sidebar.after'])
    @if ($sidebarExtras)
        <div class="lr-sidebar-extras">
            @include('larecipe::prerender.navbar')
            @if (count($boot['set']['locales']) > 1)
                <div class="lr-locale-links"><p class="lr-label lr-menu-label">{{ $t('header.languages') }}</p><ul class="lr-menu-list">@foreach ($boot['set']['locales'] as $item)<li><a class="lr-menu-item" href="{{ $item['url'] }}" lang="{{ $item['code'] }}" hreflang="{{ $item['code'] }}" dir="{{ $item['dir'] }}" data-lr-reload="" @if ($item['code'] === $boot['locale']['code']) aria-current="true" @endif>{{ $item['label'] }}</a></li>@endforeach</ul></div>
            @endif
        </div>
    @endif
</nav>
<a class="lr-scrim" href="#" aria-hidden="true" tabindex="-1"></a>
