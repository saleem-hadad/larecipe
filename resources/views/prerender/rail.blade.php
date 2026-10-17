@php($page = $boot['page'])
<aside class="lr-rail" data-lr-region="Toc">
    @if (count($page['toc']) > 0)
        <nav class="lr-toc" aria-label="On this page">
            <div class="lr-label">On this page</div>
            <ul>
                @foreach ($page['toc'] as $item)
                    <li class="lr-toc-level-{{ $item['level'] }}"><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
    <div class="lr-page-actions">
        <div class="lr-label">For agents</div>
        <a href="{{ $page['markdown_url'] }}">View as Markdown</a>
        @if ($boot['urls']['llms'])<a href="{{ $boot['urls']['llms'] }}">llms.txt</a>@endif
    </div>
    @include('larecipe::prerender.inserts', ['point' => 'toc.after', 'boot' => $boot])
</aside>
