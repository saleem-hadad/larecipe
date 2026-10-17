<?php

use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\LaRecipe;

/*
 * Assets loads the prebuilt UI and, when the host built one with
 * @larecipe/vite, the host's extension right after it.
 */

function assetsFixture(): array
{
    $root = sys_get_temp_dir().'/larecipe-assets-'.bin2hex(random_bytes(4));
    mkdir($root.'/dist/.vite', 0777, true);
    mkdir($root.'/public/build/larecipe', 0777, true);

    file_put_contents($root.'/dist/.vite/manifest.json', json_encode([
        'src/app/main.tsx' => ['file' => 'assets/app-abc.js', 'name' => 'app', 'isEntry' => true, 'imports' => ['_shared.js'], 'css' => ['assets/app-abc.css'], 'assets' => ['assets/inter-latin-wght-normal-x1.woff2', 'assets/inter-greek-wght-normal-x2.woff2']],
        '_shared.js' => ['file' => 'assets/shared-def.js'],
        'src/shims/react.ts' => ['file' => 'assets/react-r1.js', 'name' => 'react', 'isEntry' => true],
        'src/shims/larecipe-ui.ts' => ['file' => 'assets/larecipe-ui-u1.js', 'name' => 'larecipe-ui', 'isEntry' => true],
    ]));

    return [$root, new Assets(new LaRecipe(app()), null, $root.'/public', $root.'/dist')];
}

it('loads a host extension after the runtime, with its styles', function () {
    [$root, $assets] = assetsFixture();
    file_put_contents($root.'/public/build/larecipe/manifest.json', json_encode([
        'resources/larecipe/extension.tsx' => ['file' => 'extension-x9.js', 'name' => 'extension', 'isEntry' => true, 'css' => ['extension-c1.css']],
    ]));

    $tags = (string) $assets->tags('app');

    expect($assets->extensionEntry())->toBe(['file' => '/build/larecipe/extension-x9.js', 'css' => ['/build/larecipe/extension-c1.css'], 'imports' => []])
        ->and($tags)->toContain('<link rel="stylesheet" href="/build/larecipe/extension-c1.css">')
        ->and($tags)->toContain('<script type="module" src="/build/larecipe/extension-x9.js" data-lr-extension></script>')
        ->and(strpos($tags, 'app-abc.js'))->toBeLessThan(strpos($tags, 'extension-x9.js'));
});

it('shares React and @larecipe/ui with extensions through the import map', function () {
    [, $assets] = assetsFixture();

    expect($assets->importMap())->toMatchArray([
        'react' => '/larecipe-assets/'.$assets->build().'/assets/react-r1.js',
        '@larecipe/ui' => '/larecipe-assets/'.$assets->build().'/assets/larecipe-ui-u1.js',
    ]);
});

it('preloads only the Latin fonts the first paint needs', function () {
    [, $assets] = assetsFixture();
    $tags = (string) $assets->tags('app');

    expect($tags)->toContain('<link rel="preload" as="font" type="font/woff2" crossorigin href="/larecipe-assets/'.$assets->build().'/assets/inter-latin-wght-normal-x1.woff2">')
        ->and($tags)->not->toContain('inter-greek');
});

it('emits nothing for an extension the host has not built', function () {
    [, $assets] = assetsFixture();

    expect($assets->extensionEntry())->toBeNull()
        ->and($assets->extensionTags())->toBe('')
        ->and((string) $assets->tags('app'))->not->toContain('data-lr-extension');
});

it('can point at an extension built somewhere else', function () {
    [$root, $assets] = assetsFixture();
    mkdir($root.'/elsewhere');
    file_put_contents($root.'/elsewhere/manifest.json', json_encode(['x.tsx' => ['file' => 'x-1.js', 'isEntry' => true]]));

    $assets->useExtension($root.'/elsewhere', '/vendor/docs-extension/');

    expect($assets->extensionEntry()['file'])->toBe('/vendor/docs-extension/x-1.js');
});
