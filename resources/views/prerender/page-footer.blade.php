{{-- Mirrors PageFooter in ui/src/regions/PageFooter.tsx: feedback, previous and next, the edit link. --}}
@php($current = $boot['page'])
@if ($feedbackEnabled || $current['previous'] || $current['next'] || $current['edit_url'])
<footer class="lr-page-footer" data-lr-region="PageFooter">
    @if ($feedbackEnabled)
        <div class="lr-feedback lr-js-only" role="group" aria-labelledby="lr-feedback-question"><p class="lr-feedback-question" id="lr-feedback-question">{{ $t('feedback.question') }}</p><div class="lr-feedback-choices"><button type="button" aria-pressed="false" class="lr-button lr-button-outline lr-button-sm">@include('larecipe::partials.icon', ['name' => 'ThumbsUp', 'iconSize' => 15, 'iconClass' => '']){{ $t('feedback.yes') }}</button><button type="button" aria-pressed="false" class="lr-button lr-button-outline lr-button-sm">@include('larecipe::partials.icon', ['name' => 'ThumbsDown', 'iconSize' => 15, 'iconClass' => '']){{ $t('feedback.no') }}</button></div></div>
    @endif
    @if ($current['previous'] || $current['next'])
        <nav class="lr-pager" aria-label="{{ $t('footer.pager') }}">@if ($current['previous'])<a class="lr-pager-link lr-pager-prev" rel="prev" href="{{ $current['previous']['url'] }}"><span class="lr-pager-dir lr-label">@include('larecipe::partials.icon', ['name' => 'ArrowLeft', 'iconSize' => 12, 'iconClass' => '']){{ $t('footer.previous') }}</span><span class="lr-pager-title">{{ $current['previous']['title'] }}</span></a>@endif @if ($current['next'])<a class="lr-pager-link lr-pager-next" rel="next" href="{{ $current['next']['url'] }}"><span class="lr-pager-dir lr-label">{{ $t('footer.next') }}@include('larecipe::partials.icon', ['name' => 'ArrowRight', 'iconSize' => 12, 'iconClass' => ''])</span><span class="lr-pager-title">{{ $current['next']['title'] }}</span></a>@endif</nav>
    @endif
    @if ($current['edit_url'])
        <div class="lr-page-meta"><a class="lr-edit-link" href="{{ $current['edit_url'] }}" rel="noopener">@include('larecipe::partials.icon', ['name' => 'SquarePen', 'iconSize' => 15, 'iconClass' => '']){{ $t('footer.edit') }}</a></div>
    @endif
</footer>
@endif
