<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Support\TrackG;

/*
 * The Same-Rules matrix for whole pages: a page is visible to a caller in every
 * representation or in none, and a hidden page is a 404, never a 403.
 */

function staffer(bool $allowed = true): User
{
    return (new User)->forceFill(['id' => $allowed ? 1 : 2, 'name' => 'Staff', 'allowed' => $allowed]);
}

beforeEach(function () {
    TrackG::useSets([
        'gated' => TrackG::set('gated', 'gated'),
        'internal' => TrackG::set('gated', 'internal', 'gate'),
    ]);

    Gate::define('viewLarecipe', fn ($user, $page = null, $set = null) => (bool) $user->allowed);
});

dataset('representations', [
    'html' => [[], ''],
    'markdown' => [[], '.md'],
    'json' => [['Accept' => 'application/vnd.larecipe.page+json'], ''],
]);

it('hides a private version from guests in every representation', function (array $headers, string $suffix) {
    $this->get('/gated/next'.($suffix === '' ? '' : '/index'.$suffix), $headers)->assertNotFound();
})->with('representations');

it('serves a private version to an allowed reader, never publicly cached', function (array $headers, string $suffix) {
    $response = $this->actingAs(staffer())->get('/gated/next'.($suffix === '' ? '' : '/index'.$suffix), $headers)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex');
})->with('representations');

it('hides a private page from guests and from readers the gate refuses', function (array $headers, string $suffix) {
    $this->get('/gated/2.x/roadmap'.$suffix, $headers)->assertNotFound();
    $this->actingAs(staffer(false))->get('/gated/2.x/roadmap'.$suffix, $headers)->assertNotFound();
    $this->actingAs(staffer())->get('/gated/2.x/roadmap'.$suffix, $headers)->assertOk()->assertSee('ROADMAP-SECRET');
})->with('representations');

it('keeps a gate-mode set closed to everyone the gate refuses', function (array $headers, string $suffix) {
    $this->get('/internal/2.x/plans'.$suffix, $headers)->assertNotFound();
    $this->actingAs(staffer(false))->get('/internal/2.x/plans'.$suffix, $headers)->assertNotFound();

    $response = $this->actingAs(staffer())->get('/internal/2.x/plans'.$suffix, $headers)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with('representations');

it('leaves private pages and versions out of the navigation a guest gets', function () {
    $payload = $this->get('/gated/2.x', ['Accept' => 'application/vnd.larecipe.page+json'])->json();
    $paths = collect($payload['navigation'][0]['items'])->pluck('path')->all();

    expect($paths)->not->toContain('roadmap')->toContain('plans');
});
