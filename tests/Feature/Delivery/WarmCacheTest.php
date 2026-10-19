<?php

/*
 * A second request reads page lists and navigation back from the cache. The
 * test environment uses Laravel 13's defaults (a serializing store that
 * unserializes no classes), so anything but plain arrays would come back broken.
 */

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Delivery\Services\PageCatalog;

it('serves every surface from a warm cache', function (string $url, int $status) {
    $this->get($url)->assertStatus($status);

    app(PageCatalog::class)->flush();

    $this->get($url)->assertStatus($status);
})->with([
    'a page' => ['/docs/installation', 200],
    'the Markdown twin' => ['/docs/installation.md', 200],
    'llms.txt' => ['/docs/llms.txt', 200],
    'llms-full.txt' => ['/docs/llms-full.txt', 200],
    'skill.md' => ['/docs/skill.md', 200],
    'the sitemap' => ['/docs/sitemap.xml', 200],
    'a missing page' => ['/docs/nope', 404],
]);

it('round-trips a page summary through its cache shape', function () {
    $summary = new PageSummary(
        ref: new PageRef('docs', '3.x', 'ar', 'guides/deploys'),
        title: 'Deploys',
        description: 'Ship it.',
        order: 2,
        icon: 'rocket',
        hidden: true,
        badge: ['label' => 'New'],
        contentHash: 'abc',
        updatedAt: new DateTimeImmutable('2026-10-17T12:30:45.123456+04:00'),
        private: true,
    );

    expect(PageSummary::fromCache(unserialize(serialize($summary->toCache()), ['allowed_classes' => false])))->toEqual($summary);
});
