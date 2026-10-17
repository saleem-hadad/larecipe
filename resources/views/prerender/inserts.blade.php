@php
    $names = $boot['inserts'][$point] ?? [];
    $optOut = $boot['page']['inserts'] ?? [];
    $html = $names === [] || $optOut === false || (($optOut[$point] ?? true) === false)
        ? ''
        : app(\LaRecipe\Domains\Delivery\Support\InsertRenderer::class)->render($names, $boot);
@endphp
@if ($html !== '')
<div class="lr-insert" data-lr-insert="{{ $point }}">{!! $html !!}</div>
@endif
