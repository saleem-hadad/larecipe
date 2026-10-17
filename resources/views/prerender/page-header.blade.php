@php($page = $boot['page'])
<div class="lr-page-header" data-lr-region="PageHeader">
    @if ($page['fallback_from_locale'])
        <p class="lr-fallback-notice" role="note">This page is not translated yet. You are reading the original.</p>
    @endif
    <h1 class="lr-title">{{ $page['title'] }}</h1>
    @if ($page['description'])
        <p class="lr-lead">{{ $page['description'] }}</p>
    @endif
    @if ($page['updated_at'])
        <p class="lr-provenance">
            @if ($page['fresh'])<span class="lr-fresh-dot" aria-hidden="true"></span>@endif
            Updated <time datetime="{{ $page['updated_at'] }}">{{ \Illuminate\Support\Carbon::parse($page['updated_at'])->diffForHumans() }}</time>@if (! empty($page['provenance']['name'])) by {{ $page['provenance']['name'] }}@endif
        </p>
    @endif
</div>
