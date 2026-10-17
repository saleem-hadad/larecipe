@php($logo = $boot['set']['logo'])
<header class="lr-header" data-lr-region="Header">
    <a class="lr-brand" href="{{ $boot['urls']['base'] }}">
        @if ($logo['light'])
            <img class="lr-logo lr-logo-light" src="{{ $logo['light'] }}" alt="">
            <img class="lr-logo lr-logo-dark" src="{{ $logo['dark'] ?? $logo['light'] }}" alt="">
        @else
            <svg class="lr-mark" width="20" height="24" viewBox="0 0 40 48" aria-hidden="true"><path d="M6 7a3 3 0 0 1 3-3h22a3 3 0 0 1 3 3v37a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0a3.5 3.5 0 0 0-7 0Z" fill="currentColor"/><rect x="11" y="11" width="18" height="3.6" rx="1.8" class="lr-mark-cut"/><rect x="11" y="19" width="11" height="3" rx="1.5" class="lr-mark-cut"/><rect x="11" y="26" width="11" height="3" rx="1.5" class="lr-mark-cut"/></svg>
        @endif
        <span class="lr-brand-name">{{ $boot['set']['name'] }}</span>
    </a>
    @if (count($boot['set']['versions']) > 1)
        <nav class="lr-versions" aria-label="Versions">
            @foreach ($boot['set']['versions'] as $version)
                <a href="{{ $version['url'] }}" @if ($version['id'] === $boot['version']) aria-current="true" @endif>{{ $version['label'] }}</a>
            @endforeach
        </nav>
    @endif
    <div class="lr-header-spacer"></div>
    <a class="lr-search-trigger" href="{{ $boot['urls']['llms'] }}">Search the docs</a>
    @if (count($boot['set']['locales']) > 1)
        <nav class="lr-locales" aria-label="Languages">
            @foreach ($boot['set']['locales'] as $locale)
                <a href="{{ $locale['url'] }}" lang="{{ $locale['code'] }}" @if ($locale['code'] === $boot['locale']['code']) aria-current="true" @endif>{{ $locale['label'] }}</a>
            @endforeach
        </nav>
    @endif
</header>
