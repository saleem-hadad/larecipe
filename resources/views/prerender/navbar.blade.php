@php
    $navbarLinks = $boot['set']['navbar']['links'] ?? [];
    $navbarCta = $boot['set']['navbar']['cta'] ?? null;
@endphp
@if (count($navbarLinks) > 0 || $navbarCta)
<div class="lr-navbar">@foreach ($navbarLinks as $link)<a class="lr-navbar-link" href="{{ $link['href'] }}">{{ $link['label'] }}</a>@endforeach @if ($navbarCta)<a class="lr-button lr-button-outline lr-button-sm" href="{{ $navbarCta['href'] }}">{{ $navbarCta['label'] }}</a>@endif</div>
@endif
