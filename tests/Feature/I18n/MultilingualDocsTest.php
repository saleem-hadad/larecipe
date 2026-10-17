<?php

use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Sources\Actions\ListLocalizedPagesAction;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::useSets(['atlas' => TrackG::set('i18n', 'atlas')]);
});

function pagePayload(string $url): array
{
    return test()->get($url, ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->json();
}

/** @return array<string, array> navigation page items keyed by path */
function navigationPages(array $payload): array
{
    $pages = [];
    $walk = function (array $items) use (&$walk, &$pages) {
        foreach ($items as $item) {
            if ($item['type'] === 'page') {
                $pages[$item['path']] = $item;
            } elseif ($item['type'] === 'group') {
                $walk($item['items']);
            }
        }
    };

    foreach ($payload['navigation'] as $tab) {
        $walk($tab['items']);
    }

    return $pages;
}

it('lists every page of the original in another locale, translated where it can be', function () {
    $pages = navigationPages(pagePayload('/atlas/ar/1.x'));

    expect(array_keys($pages))->toEqualCanonicalizing(['index', 'install', 'notes', 'guides/deploy'])
        ->and($pages['index']['title'])->toBe('أهلًا بك')
        ->and($pages['index']['fallback'])->toBeFalse()
        ->and($pages['install']['title'])->toBe('التثبيت')
        ->and($pages['guides/deploy']['title'])->toBe('Deploy')
        ->and($pages['guides/deploy']['fallback'])->toBeTrue()
        ->and($pages['guides/deploy']['url'])->toBe('/atlas/ar/1.x/guides/deploy');
});

it('keeps the reader in their language on a page that is not translated yet', function () {
    $payload = pagePayload('/atlas/ar/1.x/guides/deploy');

    expect($payload['locale'])->toBe(['code' => 'ar', 'dir' => 'rtl'])
        ->and($payload['page']['content_locale'])->toBe(['code' => 'en', 'dir' => 'ltr'])
        ->and($payload['page']['fallback_from_locale'])->toBe('ar')
        ->and($payload['page']['path_url'])->toBe('/atlas/ar/1.x/guides/deploy')
        ->and($payload['page']['html'])->toStartWith('<div class="lr-fallback-content" lang="en" dir="ltr">')
        ->and(navigationPages($payload)['install']['title'])->toBe('التثبيت');

    $this->get('/atlas/ar/1.x/guides/deploy')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('not translated yet')
        ->assertSee('ENGLISH-ONLY')
        ->assertSee('<link rel="canonical" href="'.url('/atlas/1.x/guides/deploy').'">', false);
});

it('switches language without losing the page', function () {
    $locales = collect(pagePayload('/atlas/1.x/guides/deploy')['set']['locales'])->keyBy('code');

    expect($locales['ar']['url'])->toBe('/atlas/ar/1.x/guides/deploy')
        ->and($locales['ar']['translated'])->toBeFalse()
        ->and($locales['fr']['url'])->toBe('/atlas/fr/1.x/guides/deploy')
        ->and($locales['en']['url'])->toBe('/atlas/1.x/guides/deploy');

    $fromArabic = collect(pagePayload('/atlas/ar/1.x/install')['set']['locales'])->keyBy('code');

    expect($fromArabic['en']['url'])->toBe('/atlas/1.x/install')
        ->and($fromArabic['ar']['translated'])->toBeTrue();
});

it('emits hreflang alternates for the languages a page is written in, and x-default', function () {
    $html = $this->get('/atlas/1.x/install')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="alternate" hreflang="en" href="'.url('/atlas/1.x/install').'">')
        ->toContain('<link rel="alternate" hreflang="ar" href="'.url('/atlas/ar/1.x/install').'">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="'.url('/atlas/1.x/install').'">')
        ->not->toContain('hreflang="fr"');
});

it('renders a right-to-left locale right to left', function () {
    $this->get('/atlas/ar/1.x')->assertOk()->assertSee('<html lang="ar" dir="rtl"', false)->assertSee('أطلس يرسم');
    $this->get('/atlas/1.x')->assertOk()->assertSee('<html lang="en" dir="ltr"', false);
});

it('gives every surface the same per-locale page list', function () {
    $set = app(SetRegistry::class)->get('atlas');
    $list = app(ListLocalizedPagesAction::class);

    $arabic = $list->execute($set, '1.x', 'ar');
    $french = $list->execute($set, '1.x', 'fr');

    expect(array_keys($arabic))->toBe(['guides/deploy', 'index', 'install', 'notes'])
        ->and($arabic['install']->ref->locale)->toBe('ar')
        ->and($arabic['guides/deploy']->ref->locale)->toBe('en')
        ->and(collect($french)->map(fn ($page) => $page->ref->locale)->unique()->values()->all())->toBe(['en'])
        ->and(array_keys($list->execute($set, '1.x', 'en')))->toBe(['guides/deploy', 'index', 'install']);
});
