<?php

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\PageAddresses;

$versioned = fn () => Manifest::fromArray(['versions' => [['id' => '3.x', 'default' => true], ['id' => '2.x']], 'locales' => [['code' => 'en', 'default' => true], ['code' => 'ar']]]);

it('reads version, locale and path from an address', function () use ($versioned) {
    $addresses = new PageAddresses;

    expect($addresses->parse('3.x/billing/exports', 'kb', $versioned())->key())->toBe('kb/3.x/en/billing/exports')
        ->and($addresses->parse('2.x/ar/billing', 'kb', $versioned())->key())->toBe('kb/2.x/ar/billing')
        ->and($addresses->parse('3.x', 'kb', $versioned())->key())->toBe('kb/3.x/en/index')
        ->and($addresses->parse('guides/webhooks.md', 'kb', null)->key())->toBe('kb/current/en/guides/webhooks');
});

it('formats addresses the way it reads them', function () use ($versioned) {
    $addresses = new PageAddresses;
    $ref = new PageRef('kb', '2.x', 'ar', 'billing/exports');

    expect($addresses->format($ref, $versioned()))->toBe('2.x/ar/billing/exports')
        ->and($addresses->parse($addresses->format($ref, $versioned()), 'kb', $versioned()))->toEqual($ref)
        ->and($addresses->format(new PageRef('kb', 'current', 'en', 'index'), null))->toBe('index');
});

it('requires the version on a versioned set', function () use ($versioned) {
    (new PageAddresses)->parse('billing/exports', 'kb', $versioned());
})->throws(InvalidContentChange::class);
