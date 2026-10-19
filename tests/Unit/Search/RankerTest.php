<?php

use LaRecipe\Domains\Search\DTOs\SearchRecord;
use LaRecipe\Domains\Search\Services\Ranker;
use LaRecipe\Domains\Search\Services\SearchIndexer;
use LaRecipe\Domains\Search\Services\Tokenizer;

function rankIds(array $records, string $query): array
{
    $index = (new SearchIndexer(new Tokenizer))->build('fp', $records);
    $ranked = (new Ranker(new Tokenizer))->rank($index, $query, array_fill_keys(array_keys($records), true));

    return array_map(fn (array $hit) => $records[$hit[0]]->id, $ranked);
}

function corpus(): array
{
    return [
        new SearchRecord('installation', 'installation', 'Installation', text: 'Require the package with composer and run the migrations.'),
        new SearchRecord('webhooks', 'billing/webhooks', 'Webhooks', text: 'Acme sends a webhook for every invoice event.'),
        new SearchRecord('webhooks#retries', 'billing/webhooks', 'Webhooks', 'Retries', 'retries', 2, 'Failed deliveries are retried with exponential backoff.'),
        new SearchRecord('routing#middleware', 'routing', 'Routing', 'Middleware', 'middleware', 2, 'Wrap routes with Route::middleware so only signed in users reach them.'),
        new SearchRecord('commands#prune', 'commands', 'Artisan commands', 'Pruning orders', 'pruning-orders', 2, 'Run php artisan orders:prune nightly.'),
        new SearchRecord('faq', 'faq', 'FAQ', text: 'Middleware is mentioned here once, and webhooks too.'),
    ];
}

it('ranks title matches above text matches', function () {
    expect(rankIds(corpus(), 'webhooks')[0])->toBe('webhooks')
        ->and(rankIds(corpus(), 'middleware')[0])->toBe('routing#middleware');
});

it('finds code identifiers', function () {
    expect(rankIds(corpus(), 'Route::middleware')[0])->toBe('routing#middleware')
        ->and(rankIds(corpus(), 'orders:prune')[0])->toBe('commands#prune');
});

it('matches prefixes and tolerates small typos', function () {
    expect(rankIds(corpus(), 'migr'))->toContain('installation')
        ->and(rankIds(corpus(), 'webhoks')[0])->toBe('webhooks')
        ->and(rankIds(corpus(), 'exponentail backof'))->toContain('webhooks#retries');
});

it('prefers records that match more of the query', function () {
    expect(rankIds(corpus(), 'webhook retries')[0])->toBe('webhooks#retries');
});

it('only scores records the reader may see', function () {
    $records = corpus();
    $index = (new SearchIndexer(new Tokenizer))->build('fp', $records);
    $ranked = (new Ranker(new Tokenizer))->rank($index, 'webhooks', [5 => true]);

    expect($ranked)->toHaveCount(1)->and($ranked[0][0])->toBe(5);
});

it('returns nothing for an empty query', function () {
    expect(rankIds(corpus(), '  '))->toBe([]);
});
