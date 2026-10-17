<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Support\TrackG;

/*
 * A page's chrome (the version and language switchers, hreflang alternates)
 * follows the same rules as the pages themselves: a guest never learns that a
 * private version exists, that a page exists in another version only as a
 * private page, or that a private translation exists.
 */

beforeEach(function () {
    TrackG::useSets(['vis' => TrackG::set('visibility', 'vis')]);
    Gate::define('viewLarecipe', fn ($user = null) => (bool) ($user?->allowed ?? false));
});

function bootOf(string $html): array
{
    preg_match('#<script type="application/json" id="lr-boot">(.*?)</script>#s', $html, $matches);

    return json_decode($matches[1], true);
}

it('lists only the versions a guest may read, and never links a guest to a private page', function () {
    $html = $this->get('/vis/2.x/public')->assertOk()->getContent();
    $json = $this->get('/vis/2.x/public', ['Accept' => 'application/vnd.larecipe.page+json'])->json();

    foreach ([bootOf($html)['set'], $json['set']] as $set) {
        $versions = collect($set['versions'])->keyBy('id');

        expect($versions->keys()->all())->toBe(['2.x', '3.x'])
            ->and($versions['3.x']['url'])->toBe('/vis/3.x');
    }

    expect($html)->not->toContain('Project Falcon')->not->toContain('/vis/next');
});

it('leaves a private translation out of hreflang alternates and the language switcher', function () {
    $html = $this->get('/vis/2.x/public')->assertOk()->getContent();
    $boot = bootOf($html);

    expect(collect($boot['page']['alternates'])->pluck('hreflang')->all())->toBe(['en', 'x-default'])
        ->and(collect($boot['set']['locales'])->firstWhere('code', 'ar')['translated'])->toBeFalse()
        ->and($html)->not->toContain('hreflang="ar"');
});

it('shows a reader the gate allows everything they may read', function () {
    $staff = (new User)->forceFill(['id' => 1, 'name' => 'Staff', 'allowed' => true]);
    $boot = bootOf($this->actingAs($staff)->get('/vis/2.x/public')->assertOk()->getContent());
    $versions = collect($boot['set']['versions'])->keyBy('id');

    expect($versions->keys()->all())->toBe(['2.x', '3.x', 'next'])
        ->and($versions['3.x']['url'])->toBe('/vis/3.x/public')
        ->and(collect($boot['page']['alternates'])->pluck('hreflang')->all())->toBe(['en', 'ar', 'x-default'])
        ->and(collect($boot['set']['locales'])->firstWhere('code', 'ar')['translated'])->toBeTrue();
});
