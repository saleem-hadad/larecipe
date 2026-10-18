<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

/*
 * Redirects in larecipe.json and redirect_from in frontmatter keep old URLs
 * alive. On a versioned set, a redirect written without a version applies in
 * every version, and a target without a version stays in the reader's version.
 */

function versionedRedirectSet(string $prefix): void
{
    $root = Fixtures::temporaryDirectory('redirects');
    $base = $prefix === '' ? '' : '/'.$prefix;

    foreach (['3.x', '2.x'] as $version) {
        mkdir($root.'/'.$version, 0755, true);
        file_put_contents($root.'/'.$version.'/index.md', "# Home {$version}\n");
        file_put_contents($root.'/'.$version.'/installation.md', "# Installation {$version}\n");
    }

    file_put_contents($root.'/3.x/secret.md', "---\ntitle: Secret\nvisibility: private\nredirect_from: [/old-secret]\n---\n\n# Secret\n");
    file_put_contents($root.'/larecipe.json', json_encode([
        'name' => 'Redirects',
        'versions' => [['id' => '3.x', 'default' => true], ['id' => '2.x']],
        'redirects' => [
            ['from' => "{$base}/setup", 'to' => "{$base}/installation"],
            ['from' => "{$base}/2.x/old", 'to' => "{$base}/2.x/installation", 'status' => 302],
            ['from' => "{$base}/away", 'to' => 'https://example.com/elsewhere'],
        ],
    ]));

    TrackG::useSets(['vr' => [
        'route' => ['prefix' => $prefix, 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);
}

$location = fn ($response) => Sets::local((string) $response->headers->get('Location'));

it('runs a redirect written without a version in the requested version', function () use ($location) {
    versionedRedirectSet('vr');

    expect($location($this->get('/vr/3.x/setup')->assertStatus(301)))->toBe('/vr/3.x/installation')
        ->and($location($this->get('/vr/2.x/setup')->assertStatus(301)))->toBe('/vr/2.x/installation')
        ->and($location($this->get('/vr/setup')->assertRedirect()))->toBe('/vr/3.x/setup')
        ->and($location($this->get('/vr/2.x/away')->assertStatus(301)))->toBe('https://example.com/elsewhere');
});

it('keeps redirects written with a version in that version', function () use ($location) {
    versionedRedirectSet('vr');

    expect($location($this->get('/vr/2.x/old')->assertStatus(302)))->toBe('/vr/2.x/installation');
    $this->get('/vr/3.x/old')->assertNotFound();
});

it('runs version-less redirects on a set mounted at the root', function () use ($location) {
    versionedRedirectSet('');

    expect($location($this->get('/3.x/setup')->assertStatus(301)))->toBe('/3.x/installation')
        ->and($location($this->get('/2.x/setup')->assertStatus(301)))->toBe('/2.x/installation');
});

it('never reveals a private page through its redirect_from', function () use ($location) {
    versionedRedirectSet('vr');
    Gate::define('viewLarecipe', fn ($user = null) => $user?->id === 1);

    $this->get('/vr/3.x/old-secret')->assertNotFound();

    $this->actingAs(Sets::user(1));
    expect($location($this->get('/vr/3.x/old-secret')->assertStatus(301)))->toBe('/vr/3.x/secret');
});
