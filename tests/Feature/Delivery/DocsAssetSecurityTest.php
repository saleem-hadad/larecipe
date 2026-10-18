<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

/*
 * The docs asset route serves the images and files that live with the docs,
 * and nothing else: never a page's source, the manifest or navigation, a dot
 * file, a file of an unknown type, or a private version's files to a guest.
 */

function assetFixture(): string
{
    $root = Fixtures::temporaryDirectory('assets');
    $files = [
        'larecipe.json' => json_encode(['name' => 'Fx', 'versions' => [['id' => '2.x', 'default' => true], ['id' => 'next', 'label' => 'Project Falcon', 'visibility' => 'private']]]),
        '.git/config' => "[remote \"origin\"]\n\turl = https://token@example.com/repo.git\n",
        '_assets/shared.png' => 'shared-png',
        '2.x/index.md' => "# Home\n",
        '2.x/secret.md' => "---\nvisibility: private\n---\n\n# Merger\n\nMERGER-BODY-SECRET\n",
        '2.x/navigation.json' => '{"groups": [{"label": "NAV-LABEL-SECRET", "pages": ["index"]}]}',
        '2.x/diagram.png' => 'diagram-png',
        '2.x/.env' => 'APP_KEY=secret',
        '2.x/data.bin' => 'binary',
        '2.x/_assets/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>',
        'next/index.md' => "# Falcon\n",
        'next/_assets/falcon.png' => 'falcon-png',
    ];

    foreach ($files as $path => $contents) {
        @mkdir(dirname($root.'/'.$path), 0755, true);
        file_put_contents($root.'/'.$path, $contents);
    }

    TrackG::useSets(['fx' => [
        'route' => ['prefix' => 'fx', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    return $root;
}

beforeEach(fn () => assetFixture());

it('serves the files that live with the docs', function (string $url, string $body) {
    $response = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=3600, public');

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe($body);
})->with([
    'next to a page' => ['/fx/_assets/2.x/diagram.png', 'diagram-png'],
    'in the version\'s _assets' => ['/fx/_assets/2.x/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    'in the set\'s _assets' => ['/fx/_assets/2.x/shared.png', 'shared-png'],
    'through the current version' => ['/fx/_assets/current/diagram.png', 'diagram-png'],
]);

it('never serves a page source, the manifest, navigation, dot files or unknown types', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/fx/_assets/2.x/secret.md',
    '/fx/_assets/2.x/secret.MD',
    '/fx/_assets/2.x/secret.Md',
    '/fx/_assets/2.x/navigation.json',
    '/fx/_assets/2.x/NAVIGATION.JSON',
    '/fx/_assets/current/larecipe.json',
    '/fx/_assets/current/2.x/secret.md',
    '/fx/_assets/2.x/.env',
    '/fx/_assets/2.x/data.bin',
    '/fx/_assets/current/.git/config',
    '/fx/_assets/2.x/..%2F.git%2Fconfig',
    '/fx/_assets/2.x/_assets/../../.git/config',
]);

it('never serves a private version\'s files to a guest, nor through the current version', function () {
    Gate::define('viewLarecipe', fn ($user = null) => $user?->id === 1);

    $this->get('/fx/_assets/next/falcon.png')->assertNotFound();
    $this->get('/fx/_assets/current/next/_assets/falcon.png')->assertNotFound();

    $this->actingAs(Sets::user(1));
    $this->get('/fx/_assets/next/falcon.png')->assertOk()->assertHeader('Cache-Control', 'max-age=3600, private');
});

it('throttles the asset route', function () {
    $route = app('router')->getRoutes()->getByName('larecipe.fx.asset');

    expect($route->gatherMiddleware())->toContain('throttle:larecipe-assets');
});
