{{-- Mirrors Footer in ui/src/regions/PageFooter.tsx: the footer insert, links, text and the optional credit. --}}
@php
    $footerLinks = $boot['set']['footer']['links'] ?? [];
    $footerText = $boot['set']['footer']['text'] ?? null;
    $credit = ($boot['set']['credit'] ?? true) !== false;
    $optOut = $boot['page']['inserts'] ?? [];
    $hasFooterInsert = count($boot['inserts']['footer'] ?? []) > 0 && $optOut !== false && (($optOut['footer'] ?? true) !== false);
@endphp
@if (count($footerLinks) > 0 || $footerText || $credit || $hasFooterInsert)
<footer class="lr-footer" data-lr-region="Footer">
    <div class="lr-footer-inner">
        @include('larecipe::prerender.inserts', ['point' => 'footer'])
        @if (count($footerLinks) > 0)
            <div class="lr-footer-links">@foreach ($footerLinks as $link)<a class="lr-footer-link" href="{{ $link['href'] }}">{{ $link['label'] }}</a>@endforeach</div>
        @endif
        @if ($footerText)
            <p class="lr-footer-text">{{ $footerText }}</p>
        @endif
        @if ($credit)
            <a class="lr-credit" href="https://larecipe.saleem.dev" rel="noopener">@include('larecipe::partials.mark', ['markSize' => 14]){{ $t('footer.credit') }}</a>
        @endif
    </div>
</footer>
@endif
