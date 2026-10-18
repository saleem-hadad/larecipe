{{-- The brand link: the team's logo or the LaRecipe logo, then the name. Mirrors Brand in ui/src/regions/Header.tsx. --}}
@php($brandLogo = $boot['set']['logo'])
<a class="lr-brand" href="{{ $boot['urls']['base'] }}">@if (! $brandLogo['light'])@include('larecipe::partials.mark')@else<img class="lr-logo lr-logo-light" src="{{ $brandLogo['light'] }}" alt=""><img class="lr-logo lr-logo-dark" src="{{ $brandLogo['dark'] ?? $brandLogo['light'] }}" alt="">@endif<span class="lr-brand-name">{{ $boot['set']['name'] }}</span></a>
