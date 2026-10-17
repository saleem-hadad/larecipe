<?php

use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Services\NavigationPatcher;

$nav = fn (): NavigationTree => NavigationTree::fromJson(['groups' => [
    ['group' => 'Get started', 'pages' => ['index', 'installation']],
    ['group' => 'Billing', 'pages' => ['billing/overview', ['group' => 'Webhooks', 'pages' => ['billing/webhooks']]]],
]]);

it('adds a page to a group after a sibling', function () use ($nav) {
    $tree = (new NavigationPatcher)->apply($nav(), [['add' => 'billing/exports', 'group' => 'Billing', 'after' => 'billing/overview']]);

    expect($tree->toJson()['groups'][1]['pages'])->toBe(['billing/overview', 'billing/exports', ['group' => 'Webhooks', 'pages' => ['billing/webhooks']]]);
});

it('adds a page to a nested group, and creates a group that does not exist', function () use ($nav) {
    $tree = (new NavigationPatcher)->apply($nav(), [
        ['add' => 'billing/events', 'group' => 'Webhooks'],
        ['add' => 'agents/connect', 'group' => 'Agents'],
    ]);

    expect($tree->toJson()['groups'][1]['pages'][1]['pages'])->toBe(['billing/webhooks', 'billing/events'])
        ->and($tree->toJson()['groups'][2])->toBe(['group' => 'Agents', 'pages' => ['agents/connect']]);
});

it('removes, renames and moves pages, keeping labels', function () {
    $tree = NavigationTree::fromJson(['groups' => [
        ['group' => 'A', 'pages' => [['page' => 'one', 'label' => 'First'], 'two']],
        ['group' => 'B', 'pages' => ['three']],
    ]]);

    $tree = (new NavigationPatcher)->apply($tree, [
        ['remove' => 'two'],
        ['rename' => 'three', 'to' => 'tres'],
        ['move' => 'one', 'group' => 'B', 'before' => 'tres'],
    ]);

    expect($tree->toJson()['groups'])->toBe([
        ['group' => 'A', 'pages' => []],
        ['group' => 'B', 'pages' => [['page' => 'one', 'label' => 'First'], 'tres']],
    ]);
});

it('is safe to repeat an add', function () use ($nav) {
    $patch = [['add' => 'installation', 'group' => 'Get started', 'after' => 'index']];
    $once = (new NavigationPatcher)->apply($nav(), $patch);

    expect((new NavigationPatcher)->apply($once, $patch)->toJson())->toBe($once->toJson());
});

it('refuses operations it does not understand', function () use ($nav) {
    (new NavigationPatcher)->apply($nav(), [['explode' => 'index']]);
})->throws(InvalidContentChange::class);
