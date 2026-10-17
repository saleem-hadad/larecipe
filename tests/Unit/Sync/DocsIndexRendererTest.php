<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\Enums\ReviewMode;
use LaRecipe\Domains\Sync\Services\DocsIndexRenderer;

$line = function (string $description): string {
    $set = new CatalogSet('docs', 'Acme', 'repository', 'docs', 'database/migrations/docs', ['current'], 'current', ['en'], 'en', ReviewMode::Required);
    $page = new CatalogPage(new PageRef('docs', 'current', 'en', 'guide'), 'Guide', description: $description);
    $index = (new DocsIndexRenderer)->render($set, [$page], 8192);

    return substr($index, (int) strpos($index, '- `guide`'));
};

it('keeps a short description whole', function () use ($line) {
    expect($line('Install the package.'))->toBe('- `guide`: Guide. Install the package.');
});

it('cuts a long description at a word, never inside one', function () use ($line) {
    $text = 'People gather around the pass every evening to taste, adjust and sign off each plate before it leaves the kitchen for the dining room floor';

    expect($line($text))->toBe('- `guide`: Guide. People gather around the pass every evening to taste, adjust and sign off each plate before it leaves the kitchen for…');
});

it('ends at the last full sentence when one fits', function () use ($line) {
    $text = 'Every page is served as Markdown for agents and as HTML for people. Search, versions, languages, components and much more come with it out of the box.';

    expect($line($text))->toBe('- `guide`: Guide. Every page is served as Markdown for agents and as HTML for people.');
});
