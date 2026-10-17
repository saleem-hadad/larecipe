<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Sources\Actions\FindStaleTranslationsAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\DTOs\TranslationStatus;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    $this->docs = TrackG::copy(TrackG::fixture('i18n'));
    TrackG::useSets(['atlas' => [
        'route' => ['prefix' => 'atlas'],
        'source' => ['driver' => 'repository', 'path' => $this->docs],
    ]]);

    // Translations made after their source by default.
    foreach (['index', 'install', 'guides/deploy'] as $page) {
        touch("{$this->docs}/1.x/{$page}.md", strtotime('2026-10-01 10:00'));
    }

    touch("{$this->docs}/1.x/ar/index.md", strtotime('2026-10-02 10:00'));
    touch("{$this->docs}/1.x/ar/install.md", strtotime('2026-10-02 10:00'));
});

afterEach(function () {
    TrackG::remove($this->docs);
});

function staleTranslations(string $locale, bool $missing = false): array
{
    return app(FindStaleTranslationsAction::class)->execute(app(SetRegistry::class)->get('atlas'), '1.x', $locale, $missing);
}

it('reports nothing when every translation is newer than its source', function () {
    expect(staleTranslations('ar'))->toBe([]);
});

it('reports a translation whose source was updated after it', function () {
    touch("{$this->docs}/1.x/install.md", strtotime('2026-10-05 10:00'));

    $stale = staleTranslations('ar');

    expect($stale)->toHaveCount(1)
        ->and($stale[0]->source->path)->toBe('install')
        ->and($stale[0]->translation->locale)->toBe('ar')
        ->and($stale[0]->status)->toBe(TranslationStatus::STALE)
        ->and($stale[0]->toArray())->toMatchArray(['path' => 'install', 'locale' => 'ar', 'status' => 'stale']);
});

it('trusts the recorded source hash over file times', function () {
    $hash = app(GetPageAction::class)->execute(new PageRef('atlas', '1.x', 'en', 'index'))->contentHash;
    $translation = "{$this->docs}/1.x/ar/index.md";
    file_put_contents($translation, str_replace("title: أهلًا بك\n", "title: أهلًا بك\ntranslated_from: {$hash}\n", file_get_contents($translation)));
    touch($translation, strtotime('2026-09-01 10:00'));

    expect(collect(staleTranslations('ar'))->pluck('source.path')->all())->toBe([]);

    file_put_contents("{$this->docs}/1.x/index.md", file_get_contents("{$this->docs}/1.x/index.md")."\nA new paragraph.\n");
    touch("{$this->docs}/1.x/index.md", strtotime('2026-08-01 10:00'));

    $stale = staleTranslations('ar');

    expect($stale)->toHaveCount(1)
        ->and($stale[0]->translatedFrom)->toBe($hash)
        ->and($stale[0]->sourceHash)->not->toBe($hash)
        ->and($stale[0]->reason)->toContain('changed since this translation was made');
});

it('lists pages that have no translation yet when asked', function () {
    $missing = collect(staleTranslations('ar', missing: true))->where('status', TranslationStatus::MISSING);

    expect($missing->pluck('source.path')->all())->toBe(['guides/deploy'])
        ->and(collect(staleTranslations('fr', missing: true))->pluck('source.path')->all())->toBe(['guides/deploy', 'index', 'install']);
});

it('has nothing to say about the original language', function () {
    expect(staleTranslations('en', missing: true))->toBe([]);
});
