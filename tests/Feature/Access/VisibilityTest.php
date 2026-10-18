<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Tests\Support\TrackG;

/*
 * visibility fails closed: "public" in any case and spacing is public, no
 * value is public, and anything else (Private, privat, yes) is private, so a
 * typo can never publish a page or a version meant to stay private.
 */

beforeEach(function () {
    TrackG::useSets(['vis' => TrackG::set('visibility', 'vis')]);
    Gate::define('viewLarecipe', fn ($user = null) => false);
});

it('reads visibility case and space insensitively, and anything but public as private', function (mixed $value, Visibility $expected) {
    expect((new Frontmatter($value === 'absent' ? [] : ['visibility' => $value]))->visibility())->toBe($expected)
        ->and(Version::fromArray($value === 'absent' ? ['id' => '1.x'] : ['id' => '1.x', 'visibility' => $value])->visibility)->toBe($expected);
})->with([
    ['absent', Visibility::Public],
    ['public', Visibility::Public],
    [' PUBLIC ', Visibility::Public],
    ['private', Visibility::Private],
    ['Private', Visibility::Private],
    ['privat', Visibility::Private],
    ['', Visibility::Private],
    [true, Visibility::Private],
]);

it('hides a page whose visibility is not exactly public from guests on every surface', function () {
    $this->get('/vis/2.x/capital')->assertNotFound();
    $this->get('/vis/2.x/capital.md')->assertNotFound();
    $this->get('/vis/2.x/typo')->assertNotFound();
    $this->get('/vis/2.x/shout')->assertOk()->assertSee('SHOUT-BODY');

    $surfaces = $this->get('/vis/sitemap.xml')->getContent()
        .$this->get('/vis/llms.txt')->getContent()
        .$this->get('/vis/2.x/llms-full.txt')->streamedContent()
        .$this->get('/vis/2.x/_search?q=secret')->getContent();

    expect($surfaces)->toContain('Shouted Public')
        ->not->toContain('Capital Private')->not->toContain('CAPITAL-BODY-SECRET')
        ->not->toContain('Typo Private')->not->toContain('TYPO-BODY-SECRET');
});

it('hides a version marked Private from guests', function () {
    $this->get('/vis/next')->assertNotFound();
    $this->get('/vis/next/index.md')->assertNotFound();
});

it('reports a visibility value other than public or private', function () {
    $issues = collect(app(ValidateDocsAction::class)->execute('vis')->errors())->where('rule', 'frontmatter.schema');

    expect($issues->pluck('page')->all())->toContain('2.x/capital', '2.x/typo');
});
