<?php

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationReader;
use LaRecipe\Domains\Sources\Services\DocsMigrationOperations;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Support\Paths;

$roundTrip = function (DocsMigrationPlan $plan): DocsMigrationPlan {
    $directory = sys_get_temp_dir().'/larecipe-track-f/render-'.bin2hex(random_bytes(4));
    mkdir($directory, 0755, true);
    $file = $directory.'/2026_10_17_000000_docs_test.php';
    file_put_contents($file, (new DocsMigrationRenderer)->render(file_get_contents(Paths::stubs('docs-migration.stub')), $plan));

    return app(DocsMigrationReader::class)->read($file);
};

it('renders every operation as PHP that reads back to the same plan', function () use ($roundTrip) {
    $bodies = ["# T\n\n\tTabbed code\n  two spaces\n\nMD\n  MD;\nIt's \\ fine\n", 'no trailing newline', ''];
    $plan = new DocsMigrationPlan('2026_10_17_000000_docs_test', 'kb', new Provenance(Actor::Migration, "o'reilly", 'https://example.com/pull/1'), 'Summary with \'quotes\'', [
        DocsMigrationOperation::createPage('3.x/a', ['title' => "It's", 'order' => 2, 'ratio' => 1.0, 'hidden' => false, 'tags' => ['x', 'y'], 'badge' => ['label' => 'New']], $bodies[0]),
        DocsMigrationOperation::updatePage('3.x/ar/b', 'sha256:abc', [], $bodies[1]),
        DocsMigrationOperation::updatePage('3.x/c', null, ['title' => 'C'], $bodies[2]),
        DocsMigrationOperation::movePage('3.x/a', '3.x/d', 'sha256:def'),
        DocsMigrationOperation::deletePage('3.x/e', 'sha256:ghi'),
        DocsMigrationOperation::updateNavigation('3.x', 'absent', tree: ['groups' => [['group' => 'G', 'pages' => ['a', 'b']]]], locale: 'ar'),
        DocsMigrationOperation::updateNavigation('', null, patch: [['add' => 'x', 'group' => 'G']]),
        DocsMigrationOperation::updateManifest('sha256:jkl', ['name' => 'Acme', 'favicon' => null]),
    ]);

    $read = $roundTrip($plan);

    expect($read->operations)->toEqual($plan->operations)
        ->and($read->set)->toBe('kb')
        ->and($read->summary)->toBe("Summary with 'quotes'")
        ->and($read->author->name)->toBe("o'reilly")
        ->and($read->author->ref)->toBe('https://example.com/pull/1');
});

it('keeps page bodies readable as Markdown in the diff', function () {
    $code = (new DocsMigrationRenderer)->operation(DocsMigrationOperation::updatePage('installation', 'sha256:1', ['title' => 'Installation'], "# Installation\n\nRun it.\n"));

    expect($code)->toBe(implode("\n", [
        "        \$this->updatePage('installation', basedOn: 'sha256:1', frontmatter: [",
        "            'title' => 'Installation',",
        "        ], body: <<<'MD'",
        '            # Installation',
        '',
        '            Run it.',
        '',
        '            MD);',
    ]));
});

it('merges a session into the fewest operations that replay it', function () {
    $ops = new DocsMigrationOperations;
    $merge = fn (array $operations, DocsMigrationOperation ...$next) => array_reduce($next, fn ($carry, $op) => $ops->merge($carry, $op), $operations);

    $created = $merge([], DocsMigrationOperation::createPage('a', [], "1\n"), DocsMigrationOperation::updatePage('a', 'h1', [], "2\n"), DocsMigrationOperation::movePage('a', 'b', 'h2'));
    $cancelled = $merge([], DocsMigrationOperation::createPage('a', [], "1\n"), DocsMigrationOperation::deletePage('a', 'h1'));
    $deleted = $merge([], DocsMigrationOperation::updatePage('a', 'h0', [], "1\n"), DocsMigrationOperation::deletePage('a', 'h1'));
    $manifest = $merge([], DocsMigrationOperation::updateManifest('m0', ['name' => 'A']), DocsMigrationOperation::updateManifest('m1', ['theme' => ['accent' => 'red']]));

    expect($created)->toEqual([DocsMigrationOperation::createPage('b', [], "2\n")])
        ->and($cancelled)->toBe([])
        ->and($deleted)->toEqual([DocsMigrationOperation::deletePage('a', 'h0')])
        ->and($manifest[0]->basedOn)->toBe('m0')
        ->and($manifest[0]->patch)->toBe(['name' => 'A', 'theme' => ['accent' => 'red']])
        ->and($merge([], DocsMigrationOperation::updatePage('a', 'h0', [], "1\n"), DocsMigrationOperation::updatePage('z', 'h9', [], "9\n"))[1]->type)
        ->toBe(DocsMigrationOperationType::UpdatePage);
});
