{{-- Mirrors NavList and NavEntry in ui/src/regions/Sidebar.tsx. --}}
@php
    $containsActive = function (array $items) use (&$containsActive): bool {
        foreach ($items as $item) {
            if (($item['type'] === 'page' && ! empty($item['active'])) || ($item['type'] === 'group' && $containsActive($item['items']))) {
                return true;
            }
        }

        return false;
    };
@endphp
<ul class="lr-nav-list">
    @foreach ($items as $item)
        @if ($item['type'] === 'group')
            @if ($depth > 0 || ($item['collapsed'] ?? false) === true)
                <li class="lr-nav-group"><details @if (! ($item['collapsed'] ?? false) || $containsActive($item['items'])) open @endif><summary class="lr-nav-label lr-nav-summary"><span>{{ $item['label'] }}</span>@include('larecipe::partials.icon', ['name' => 'ChevronDown', 'iconSize' => 14, 'iconClass' => ''])</summary>@include('larecipe::prerender.nav-items', ['items' => $item['items'], 'depth' => $depth + 1])</details></li>
            @else
                <li class="lr-nav-group"><div class="lr-nav-label">{{ $item['label'] }}</div>@include('larecipe::prerender.nav-items', ['items' => $item['items'], 'depth' => $depth + 1])</li>
            @endif
        @elseif ($item['type'] === 'page')
            <li><a class="lr-nav-link" href="{{ $item['url'] }}" @if (! empty($item['active'])) aria-current="page" @endif><span class="lr-nav-text">{{ $item['title'] }}</span>@if (! empty($item['badge']['label']))<span class="lr-badge" @if (! empty($item['badge']['tone'])) data-tone="{{ $item['badge']['tone'] }}" @endif>{{ $item['badge']['label'] }}</span>@endif @if (! empty($item['fresh']))<span class="lr-fresh-dot" title="{{ $t('nav.updated') }}"><span class="lr-sr">{{ $t('nav.updated') }}</span></span>@endif</a></li>
        @else
            <li><a class="lr-nav-link" href="{{ $item['href'] }}" rel="noopener"><span class="lr-nav-text">{{ $item['label'] }}</span>@include('larecipe::partials.icon', ['name' => 'ArrowUpRight', 'iconSize' => 13, 'iconClass' => 'lr-nav-external'])</a></li>
        @endif
    @endforeach
</ul>
