{{-- An insert point: the server fallbacks of the components placed here, mounted as islands by React (ui/src/app/Insert.tsx). --}}
@php
    $insertNames = $boot['inserts'][$point] ?? [];
    $insertOptOut = $boot['page']['inserts'] ?? [];
    $insertHtml = $insertNames === [] || $insertOptOut === false || (($insertOptOut[$point] ?? true) === false)
        ? ''
        : app(\LaRecipe\Domains\Delivery\Support\InsertRenderer::class)->render($insertNames, $boot);
@endphp
@if ($insertHtml !== '')
<div class="lr-insert" data-lr-insert="{{ $point }}">{!! $insertHtml !!}</div>
@endif
