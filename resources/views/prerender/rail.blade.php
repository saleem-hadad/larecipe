{{-- Mirrors ui/src/regions/Rail.tsx: table of contents, "For agents" actions, the Last change ticket. --}}
@php
    $current = $boot['page'];
    $toc = array_values(array_filter($current['toc'], fn ($item) => $item['level'] <= 4));
    $ticket = $current['ticket'] ?? null;
    $facts = [];

    if ($ticket) {
        if (! empty($ticket['ref'])) {
            $facts[] = '<span class="lr-hash">'.e($ticket['ref']).'</span>';
        }
        if (! empty($ticket['pages_label'])) {
            $facts[] = '<span>'.e($ticket['pages_label']).'</span>';
        }
        if ($ticket['additions'] !== null || $ticket['deletions'] !== null) {
            $facts[] = '<span class="lr-hash">+'.(int) $ticket['additions'].' −'.(int) $ticket['deletions'].'</span>';
        }
    }
@endphp
<aside class="lr-rail" data-lr-region="Toc">
    @if (count($toc) > 0)
        <nav class="lr-rail-section lr-toc" aria-labelledby="lr-toc-label"><p class="lr-label" id="lr-toc-label">{{ $t('page.on_this_page') }}</p><ul class="lr-toc-list">@foreach ($toc as $index => $item)<li class="lr-toc-item lr-toc-level-{{ $item['level'] }}"><a class="lr-toc-link" href="#{{ $item['id'] }}" @if ($index === 0) data-active="" aria-current="true" @endif>{{ $item['text'] }}</a></li>@endforeach</ul></nav>
    @endif
    @include('larecipe::prerender.page-actions', ['variant' => 'rail'])
    @if ($ticket)
        <article class="lr-ticket" aria-labelledby="lr-ticket-label"><header class="lr-ticket-head"><span id="lr-ticket-label">{{ $t('ticket.label') }}@if ($ticket['number'])<span class="lr-hash">#{{ $ticket['number'] }}</span>@endif</span>@if ($ticket['time'])<span>@if ($current['fresh'])<span class="lr-fresh-dot" aria-hidden="true"></span>@endif<time class="lr-hash" datetime="{{ $ticket['at'] }}">{{ $ticket['time'] }}</time></span>@endif</header><div class="lr-perf" aria-hidden="true"></div><p class="lr-ticket-summary">{{ $ticket['summary'] }}</p>@if (count($facts) > 0 || ! empty($ticket['approved_by']))<div class="lr-ticket-meta">@if (count($facts) > 0)<span class="lr-ticket-meta-line">{!! implode('<span aria-hidden="true"> · </span>', $facts) !!}</span>@endif @if (! empty($ticket['approved_by']))<span class="lr-ticket-meta-line">{!! str_replace(':name', '<bdi>'.e($ticket['approved_by']).'</bdi>', e($t('ticket.approved_by'))) !!}</span>@endif</div>@endif @if ($ticket['url'])<a class="lr-ticket-action" href="{{ $ticket['url'] }}" rel="noopener">{{ $t('ticket.view') }}@include('larecipe::partials.icon', ['name' => 'ArrowRight', 'iconSize' => 12, 'iconClass' => ''])</a>@endif</article>
    @endif
    @include('larecipe::prerender.inserts', ['point' => 'toc.after'])
</aside>
