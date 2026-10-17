<?php

use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;
use LaRecipe\Domains\Upgrade\Services\ConfigMapper;
use LaRecipe\Domains\Upgrade\Services\ProjectScanner;
use LaRecipe\Domains\Upgrade\Services\SidebarParser;
use LaRecipe\Domains\Upgrade\Services\V2Links;
use LaRecipe\Tests\Support\TrackG;

function v2Config(array $overrides = []): array
{
    return array_replace_recursive([
        'docs' => ['route' => '/handbook', 'path' => '/resources/docs', 'landing' => 'overview', 'middleware' => ['web']],
        'versions' => ['default' => '2.0', 'published' => ['2.0', '1.0']],
        'settings' => ['auth' => false, 'guard' => null, 'ga_id' => '', 'middleware' => ['web', 'docs']],
        'search' => ['default' => 'algolia', 'engines' => ['algolia' => ['key' => 'SECRET-ALGOLIA-KEY', 'index' => 'x']]],
        'ui' => ['colors' => ['primary' => '#123456', 'secondary' => '#654321']],
    ], $overrides);
}

it('maps the v2 config to a v3 config and manifest without copying secrets', function () {
    $mapped = (new ConfigMapper)->map(new V2Project('/app', v2Config(), 'Acme'));
    $config = eval('?>'.str_replace('base_path(', 'trim(', $mapped['config']));

    expect($config['sets']['docs']['route'])->toBe(['prefix' => 'handbook', 'domain' => null, 'middleware' => ['web', 'docs']])
        ->and($config['sets']['docs']['access']['mode'])->toBe('public')
        ->and($mapped['manifest']['name'])->toBe('Acme')
        ->and($mapped['manifest']['versions'])->toBe([['id' => '2.0', 'default' => true], ['id' => '1.0']])
        ->and($mapped['manifest']['theme'])->toBe(['accent' => '#123456'])
        ->and(json_encode($mapped))->not->toContain('SECRET-ALGOLIA-KEY')
        ->and(collect($mapped['entries'])->where('kind', ReportEntry::DROPPED)->pluck('message')->implode(' '))->toContain('Algolia', 'ui.colors.secondary');
});

it('maps settings.auth to auth mode and a defined viewLarecipe gate to gate mode', function () {
    $mapper = new ConfigMapper;

    expect($mapper->map(new V2Project('/app', v2Config(['settings' => ['auth' => true]])))['config'])->toContain("'mode' => 'auth'")
        ->and($mapper->map(new V2Project('/app', v2Config()), gated: true)['config'])->toContain("'mode' => 'gate'")
        ->and($mapper->map(new V2Project('/app', v2Config(), moveTo: 'docs'))['config'])->toContain("base_path('docs')");
});

it('parses a v2 sidebar into navigation.json', function () {
    $sidebar = <<<'MD'
        - [Home](/{{route}}/{{version}}/overview)
        - ## Getting started
            - [Install](/{{route}}/{{version}}/installation)
            - [Configure it](/handbook/{{version}}/configuration)
        - ## Elsewhere
            - [GitHub](https://github.com/acme/acme)
        Some stray text.
        MD;

    $parsed = (new SidebarParser)->parse($sidebar, '2.0', new V2Links('handbook', false), ['overview' => 'Home', 'installation' => 'Install', 'configuration' => 'Configuration'], 'index.md');

    expect($parsed['navigation']['groups'])->toBe([
        'overview',
        ['group' => 'Getting started', 'pages' => ['installation', ['page' => 'configuration', 'label' => 'Configure it']]],
        ['group' => 'Elsewhere', 'pages' => [['label' => 'GitHub', 'href' => 'https://github.com/acme/acme']]],
    ])->and(collect($parsed['entries'])->where('kind', ReportEntry::MANUAL)->count())->toBe(1);
});

it('finds what it cannot convert outside the docs', function () {
    $base = TrackG::copy(TrackG::fixture('v2'));
    file_put_contents($base.'/app/Providers/DocsServiceProvider.php', implode("\n", [
        '<?php',
        'use BinaryTorch\LaRecipe\LaRecipe;',
        'LaRecipe::script("custom", __DIR__."/custom.js");',
        'Gate::define("viewLarecipe", fn ($user, $documentation) => true);',
    ]));
    $composer = json_decode(file_get_contents($base.'/composer.json'), true);
    $composer['require']['binarytorch/larecipe-dark-theme'] = '^2.0';
    file_put_contents($base.'/composer.json', json_encode($composer));
    mkdir($base.'/larecipe-components');

    $scan = (new ProjectScanner)->scan(new V2Project($base, require $base.'/config/larecipe.php'));
    $manual = collect($scan['entries'])->where('kind', ReportEntry::MANUAL);

    expect($scan['gated'])->toBeTrue()
        ->and($manual->pluck('category')->unique()->values()->all())->toBe(['namespace', 'assets', 'gate', 'views', 'themes'])
        ->and($manual->firstWhere('category', 'assets')->file)->toBe('app/Providers/DocsServiceProvider.php')
        ->and($manual->firstWhere('category', 'assets')->line)->toBe(3)
        ->and($scan['composer'])->toBe(['composer remove binarytorch/larecipe', 'composer remove binarytorch/larecipe-dark-theme', 'composer require larecipe/larecipe']);

    TrackG::remove($base);
});
