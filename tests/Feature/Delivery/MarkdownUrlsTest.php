<?php

/*
 * llms.txt promises that adding .md to any page URL gives its Markdown. That
 * includes the URLs that end at a set, a version or a language.
 */

it('serves Markdown when .md is added to any page URL', function (string $page) {
    // Unversioned URLs of a versioned set redirect to the default version, as pages do.
    $twin = $this->followingRedirects()->get($page.'.md')->assertOk();

    expect($twin->headers->get('Content-Type'))->toStartWith('text/markdown')
        ->and($twin->getContent())->toBe($this->withHeaders(['Accept' => 'text/markdown'])->followingRedirects()->get($page)->getContent());
})->with([
    'a page' => ['/docs/installation'],
    'the set root' => ['/docs'],
    'a version root' => ['/handbook/2.x'],
    'a language root' => ['/handbook/ar/2.x'],
    'the default version root' => ['/handbook'],
]);
