<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

/*
 * A signed-in reader's response can carry what guests never see (private
 * pages in the navigation), so it is never publicly cacheable, in any format,
 * and never shares an ETag with the guest response.
 */

beforeEach(function () {
    $root = Fixtures::temporaryDirectory('personal');
    file_put_contents($root.'/index.md', "# Home\n");
    file_put_contents($root.'/public.md', "# Public page\n\nPublic text.\n");
    file_put_contents($root.'/secret.md', "---\ntitle: Top Secret Merger\nvisibility: private\n---\n\n# Top Secret Merger\n");

    TrackG::useSets(['fx' => [
        'route' => ['prefix' => 'fx', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    Gate::define('viewLarecipe', fn ($user = null) => $user?->id === 1);
});

it('keeps a signed-in reader\'s page out of shared caches in every format', function (array $headers) {
    $guest = $this->get('/fx/public', $headers)->assertOk();

    expect($guest->headers->get('Cache-Control'))->toContain('public')
        ->and($guest->getContent())->not->toContain('Top Secret Merger');

    $this->actingAs(Sets::user(1));
    $reader = $this->get('/fx/public', $headers)->assertOk();

    expect($reader->headers->get('Cache-Control'))->toContain('private')->not->toContain('public')
        ->and($reader->headers->get('Vary'))->toContain('Cookie')
        ->and($reader->headers->get('ETag'))->not->toBe($guest->headers->get('ETag'));
})->with([
    'HTML' => [[]],
    'navigation JSON' => [['Accept' => 'application/vnd.larecipe.page+json']],
    'Markdown' => [['Accept' => 'text/markdown']],
]);

it('never answers a signed-in reader with the guest\'s 304', function () {
    $etag = $this->get('/fx/public', ['Accept' => 'application/vnd.larecipe.page+json'])->headers->get('ETag');

    $this->actingAs(Sets::user(1));

    $this->get('/fx/public', ['Accept' => 'application/vnd.larecipe.page+json', 'If-None-Match' => $etag])
        ->assertOk()
        ->assertSee('Top Secret Merger');
});
