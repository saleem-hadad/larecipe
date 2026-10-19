<?php

use LaRecipe\Domains\Markdown\Actions\ParsePageAction;
use LaRecipe\Domains\Search\DTOs\SearchRecord;
use LaRecipe\Domains\Search\Services\SearchRecordExtractor;
use LaRecipe\Tests\Unit\Markdown\Render;

function records(string $body, array $frontmatter = []): array
{
    $page = Render::page($body, 'billing/webhooks', $frontmatter);

    return (new SearchRecordExtractor)->extract($page, app(ParsePageAction::class)->execute($page, null, ['purpose' => 'search']));
}

it('splits a page into an intro and one record per H2 and H3', function () {
    $records = records("# Webhooks\n\nIntro text.\n\n## Verify\n\nCheck the `X-Signature` header.\n\n### Details\n\nMore.\n\n#### Deep\n\nStill details.", ['description' => 'Receive events.', 'tags' => ['events']]);

    expect(array_map(fn (SearchRecord $r) => [$r->id, $r->heading, $r->level, $r->text], $records))->toBe([
        ['billing/webhooks', null, 1, 'Receive events. Intro text.'],
        ['billing/webhooks#verify', 'Verify', 2, 'Check the X-Signature header.'],
        ['billing/webhooks#details', 'Details', 3, 'More. Deep Still details.'],
    ])->and($records[1]->anchor)->toBe('verify')
        ->and($records[1]->title)->toBe('Example')
        ->and($records[1]->tags)->toBe(['events']);
});

it('indexes component text, code and titles', function () {
    $records = records("<Steps>\n<Step title=\"Install\">\nRun it.\n</Step>\n</Steps>\n\n```php\nRoute::get('/');\n```\n\n| Key | Value |\n| --- | --- |\n| `queue` | redis |");

    expect($records[0]->text)->toBe("Install Run it. Route::get('/'); Key Value queue redis");
});

it('gives gated blocks their own records with the ability they need', function () {
    $records = records(<<<'MD'
Open text.

## Exports

<Can ability="view-billing">
Export invoices as CSV.

## Secret heading

Gated detail.
</Can>

<Cannot ability="view-billing">
Ask your owner.
</Cannot>

After the gates.
MD);

    expect(array_map(fn (SearchRecord $r) => [$r->heading, $r->abilities, $r->text], $records))->toBe([
        [null, [], 'Open text.'],
        ['Exports', [], ''],
        ['Exports', ['view-billing'], 'Export invoices as CSV.'],
        ['Secret heading', ['view-billing'], 'Gated detail.'],
        ['Exports', ['!view-billing'], 'Ask your owner.'],
        ['Exports', [], 'After the gates.'],
    ])->and(array_unique(array_map(fn (SearchRecord $r) => $r->id, $records)))->toHaveCount(6);
});

it('checks ability requirements, including negated ones', function () {
    $can = new SearchRecord('a', 'a', 'A', abilities: ['view-billing']);
    $cannot = new SearchRecord('b', 'b', 'B', abilities: ['!view-billing']);

    expect($can->allows([]))->toBeFalse()
        ->and($can->allows(['view-billing']))->toBeTrue()
        ->and($cannot->allows([]))->toBeTrue()
        ->and($cannot->allows(['view-billing']))->toBeFalse();
});

it('always yields a record for a page, even an empty one', function () {
    $records = records('', ['description' => 'Only a description.']);

    expect($records)->toHaveCount(1)->and($records[0]->text)->toBe('Only a description.');
});
