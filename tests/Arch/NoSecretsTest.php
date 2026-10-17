<?php

use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use Symfony\Component\Finder\Finder;

/*
 * The No-Secrets Rule: no component, resolver or agent surface ever reads
 * configuration or environment values. There is no <Config> and no <Env>. The
 * References provider is the one place that reads config (the model allowlist
 * and the event namespaces) to hand it to the resolvers.
 */

arch('live references never read configuration or environment values')
    ->expect('LaRecipe\Domains\References')
    ->not->toUse([
        'config',
        'env',
        'getenv',
        'Illuminate\Support\Facades\Config',
        'Illuminate\Support\Env',
        'Illuminate\Contracts\Config\Repository',
        'Illuminate\Config\Repository',
    ])
    ->ignoring('LaRecipe\Domains\References\ReferencesServiceProvider');

it('keeps references away from every other door to configuration and the environment', function () {
    $files = Finder::create()->files()->in(dirname(__DIR__, 2).'/src/Domains/References')->name('*.php')->notName('ReferencesServiceProvider.php');

    foreach ($files as $file) {
        expect(file_get_contents($file->getRealPath()))
            ->not->toMatch('/\bconfig\s*\(|\benv\s*\(|\bgetenv\s*\(|\$_ENV|\$_SERVER|Config::|[\'"]config[\'"]/', $file->getFilename().' reads configuration or the environment');
    }
});

it('ships no <Config> or <Env> component', function () {
    $registry = app(ComponentRegistry::class);

    expect($registry->has('Config'))->toBeFalse()->and($registry->has('Env'))->toBeFalse();
});
