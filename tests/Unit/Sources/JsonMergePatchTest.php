<?php

use LaRecipe\Domains\Sources\Services\JsonMergePatch;

it('applies RFC 7386 merge patches', function (array $target, array $patch, array $expected) {
    expect((new JsonMergePatch)->apply($target, $patch))->toBe($expected);
})->with([
    'replace a key' => [['name' => 'Acme'], ['name' => 'Acme Docs'], ['name' => 'Acme Docs']],
    'add a key' => [['name' => 'Acme'], ['landing' => 'intro'], ['name' => 'Acme', 'landing' => 'intro']],
    'remove with null' => [['name' => 'Acme', 'favicon' => '/f.svg'], ['favicon' => null], ['name' => 'Acme']],
    'merge objects' => [['theme' => ['accent' => 'red', 'radius' => 'sharp']], ['theme' => ['accent' => 'blue']], ['theme' => ['accent' => 'blue', 'radius' => 'sharp']]],
    'replace lists whole' => [['versions' => [['id' => '1.x'], ['id' => '2.x']]], ['versions' => [['id' => '3.x']]], ['versions' => [['id' => '3.x']]]],
    'nulls inside new objects are dropped' => [[], ['navbar' => ['cta' => null, 'links' => []]], ['navbar' => ['links' => []]]],
]);

it('composes two patches into one with the same effect', function () {
    $patch = new JsonMergePatch;
    $target = ['name' => 'Acme', 'theme' => ['accent' => 'red', 'radius' => 'sharp'], 'favicon' => '/f.svg'];
    $first = ['theme' => ['accent' => 'blue'], 'landing' => 'intro'];
    $second = ['theme' => ['radius' => 'soft'], 'favicon' => null];

    expect($patch->apply($target, $patch->compose($first, $second)))
        ->toBe($patch->apply($patch->apply($target, $first), $second));
});
