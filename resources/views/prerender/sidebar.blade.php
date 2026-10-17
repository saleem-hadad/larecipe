<nav class="lr-sidebar" aria-label="Docs" data-lr-region="Sidebar">
    @include('larecipe::prerender.inserts', ['point' => 'sidebar.before', 'boot' => $boot])
    @foreach ($boot['navigation'] as $tab)
        @if ($tab['label'])
            <div class="lr-nav-tab">{{ $tab['label'] }}</div>
        @endif
        @include('larecipe::prerender.nav-items', ['items' => $tab['items']])
    @endforeach
    @include('larecipe::prerender.inserts', ['point' => 'sidebar.after', 'boot' => $boot])
</nav>
