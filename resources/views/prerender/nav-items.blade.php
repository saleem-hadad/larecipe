<ul class="lr-nav-list">
    @foreach ($items as $item)
        @if ($item['type'] === 'group')
            <li class="lr-nav-group">
                <div class="lr-nav-label">{{ $item['label'] }}</div>
                @include('larecipe::prerender.nav-items', ['items' => $item['items']])
            </li>
        @elseif ($item['type'] === 'page')
            <li><a class="lr-nav-link" href="{{ $item['url'] }}" @if (! empty($item['active'])) aria-current="page" @endif>{{ $item['title'] }}</a></li>
        @else
            <li><a class="lr-nav-link" href="{{ $item['href'] }}" rel="noopener">{{ $item['label'] }}</a></li>
        @endif
    @endforeach
</ul>
