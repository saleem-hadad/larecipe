@php($page = $boot['page'])
<footer class="lr-page-footer" data-lr-region="PageFooter">
    <nav class="lr-pager" aria-label="Previous and next">
        @if ($page['previous'])
            <a class="lr-pager-link" rel="prev" href="{{ $page['previous']['url'] }}"><span class="lr-pager-dir">Previous</span><span class="lr-pager-title">{{ $page['previous']['title'] }}</span></a>
        @endif
        @if ($page['next'])
            <a class="lr-pager-link lr-pager-next" rel="next" href="{{ $page['next']['url'] }}"><span class="lr-pager-dir">Next</span><span class="lr-pager-title">{{ $page['next']['title'] }}</span></a>
        @endif
    </nav>
    @if ($page['edit_url'])
        <a class="lr-edit-link" href="{{ $page['edit_url'] }}" rel="noopener">Edit this page</a>
    @endif
</footer>
