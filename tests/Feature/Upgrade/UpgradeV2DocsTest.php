<?php

use LaRecipe\Domains\Upgrade\Actions\PlanUpgradeAction;
use LaRecipe\Domains\Upgrade\Actions\ValidateUpgradeAction;
use LaRecipe\Domains\Upgrade\DTOs\V2Project;
use LaRecipe\Tests\Support\TrackG;

/*
 * The upgrade, run on a copy of the real LaRecipe 2.2 docs (larecipe-docs).
 */

beforeEach(function () {
    $this->base = TrackG::copy(TrackG::fixture('v2'));
    app()->setBasePath($this->base);
});

afterEach(function () {
    TrackG::remove($this->base);
});

/** Every page file under a folder, without its fenced code samples. */
function proseOf(string $folder): array
{
    $pages = [];

    foreach (glob($folder.'/*.md') as $file) {
        $pages[basename($file)] = preg_replace('/^```.*?^```/ms', '', (string) file_get_contents($file));
    }

    return $pages;
}

it('writes only the report on a dry run', function () {
    $before = md5(serialize(array_map('md5_file', glob($this->base.'/resources/docs/2.2/*'))));

    $this->artisan('larecipe:upgrade')
        ->expectsOutputToContain('Dry run: nothing was written')
        ->assertSuccessful();

    expect(md5(serialize(array_map('md5_file', glob($this->base.'/resources/docs/2.2/*')))))->toBe($before)
        ->and(file_get_contents($this->base.'/config/larecipe.php'))->toContain("'versions'")
        ->and($this->base.'/larecipe-upgrade-report.md')->toBeFile()
        ->and($this->base.'/larecipe-upgrade-report.json')->toBeFile();

    $report = json_decode(file_get_contents($this->base.'/larecipe-upgrade-report.json'), true);

    expect($report['applied'])->toBeFalse()
        ->and($report['versions'])->toBe(['2.2'])
        ->and($report['summary']['pages'])->toBe(18)
        ->and($report['composer'])->toBe(['composer remove binarytorch/larecipe', 'composer require larecipe/larecipe'])
        ->and(collect($report['manual'])->pluck('category')->unique()->values()->all())->toContain('views', 'pages');
});

it('converts config, navigation and content with --apply', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true])->assertSuccessful();

    $docs = $this->base.'/resources/docs';
    $config = require $this->base.'/config/larecipe.php';
    $manifest = json_decode(file_get_contents($docs.'/larecipe.json'), true);
    $navigation = json_decode(file_get_contents($docs.'/2.2/navigation.json'), true);

    expect($config['sets']['docs']['route']['prefix'])->toBe('docs')
        ->and($config['sets']['docs']['source']['path'])->toBe($this->base.'/resources/docs')
        ->and($config['sets']['docs']['access']['mode'])->toBe('public')
        ->and($this->base.'/config/larecipe.v2.php.bak')->toBeFile()
        ->and($manifest)->toMatchArray(['landing' => 'overview', 'favicon' => '/images/logo.png', 'theme' => ['accent' => '#787AF6']])
        ->and($manifest['versions'])->toBe([['id' => '2.2', 'default' => true]])
        ->and(collect($navigation['groups'])->pluck('group')->all())->toBe(['Get Started', 'Features', 'Customization', 'Usage Examples'])
        ->and($navigation['groups'][0]['pages'][0])->toBe(['page' => 'overview', 'label' => 'Overview'])
        ->and($docs.'/2.2/index.md')->not->toBeFile();

    // The secrets in the v2 config (an Algolia key) never travel to the new files.
    expect(file_get_contents($docs.'/larecipe.json').file_get_contents($this->base.'/config/larecipe.php').file_get_contents($this->base.'/larecipe-upgrade-report.md'))
        ->not->toContain('01b739117b5d45d29358cfba9f684e02');

    foreach (proseOf($docs.'/2.2') as $file => $prose) {
        expect($prose)
            ->not->toContain('<larecipe-', "{$file} still has a v2 component")
            ->not->toMatch('/^>\s*\{(primary|info|success|warning|danger)/m')
            ->not->toMatch('/^<a name=/m');
    }

    $configurations = file_get_contents($docs.'/2.2/configurations.md');

    expect($configurations)
        ->toStartWith("---\ntitle: 'LaRecipe Configurations And General Setup'\n")
        ->toContain("## Routing {#routing}\n")
        ->toContain("> [!WARNING]\n> LaRecipe will try to locate `index.md`")
        ->toContain("- guest users\n- authenticated users and\n- a subset(s) of authenticated users");

    expect(file_get_contents($docs.'/2.2/example-documentations.md'))
        ->toContain('<Button href="https://zino.binarytorch.com.my" variant="primary">View Zino Library</Button>');

    // Code samples are left exactly as they were.
    expect(file_get_contents($docs.'/2.2/vue-components-support.md'))
        ->toContain("```html\n<larecipe-card shadow>\n    Example card\n</larecipe-card>\n```");
});

it('produces a set that renders and passes the basic checks', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true])->assertSuccessful();

    TrackG::useSets(['upgraded' => [
        'route' => ['prefix' => 'upgraded', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $this->base.'/resources/docs'],
    ]]);

    $navigation = $this->get('/upgraded/2.2/overview', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->json('navigation.0.items');
    $paths = collect($navigation)->flatMap(fn ($group) => collect($group['items'])->pluck('path'))->all();

    expect($paths)->toHaveCount(17);

    foreach ($paths as $path) {
        $html = $this->get("/upgraded/2.2/{$path}")->assertOk()->getContent();

        expect($html)->not->toContain('<p>&lt;larecipe-')->not->toContain('<p>&lt;Card')->not->toContain('<p>&lt;/Card');
    }

    // GitHub alert rendering belongs to the Markdown renderer; here the alert is at least a quote.
    $this->get('/upgraded/2.2/configurations')->assertSee('id="routing"', false)->assertSee('<aside class="lr-callout" data-type="warning" role="note">', false);
    $this->get('/upgraded/2.2/overview.md')->assertOk()->assertSee('description: ');
    $this->get('/upgraded/2.2/example-documentations')->assertSee('href="https://zino.binarytorch.com.my">View Zino Library</a>', false);
    $this->get('/upgraded/2.2/example-project-tracking')->assertSee('<span class="lr-badge" data-tone="success">Completed</span>', false);

    $validation = app(ValidateUpgradeAction::class)->execute(app(PlanUpgradeAction::class)->execute(
        new V2Project($this->base, require $this->base.'/config/larecipe.v2.php.bak'),
    ));

    expect($validation['errors'])->toBe(0, json_encode(array_filter($validation['issues'], fn ($issue) => $issue['level'] === 'error')));
});

it('moves the docs with --move-to', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true, '--move-to' => 'docs'])->assertSuccessful();

    $config = require $this->base.'/config/larecipe.php';

    expect($config['sets']['docs']['source']['path'])->toBe($this->base.'/docs')
        ->and($this->base.'/docs/larecipe.json')->toBeFile()
        ->and($this->base.'/docs/2.2/navigation.json')->toBeFile()
        ->and($this->base.'/docs/2.2/overview.md')->toBeFile()
        ->and(glob($this->base.'/docs/2.2/*.md'))->toHaveCount(18)
        ->and(is_dir($this->base.'/resources/docs'))->toBeFalse();
});

it('rewrites /docs/{{version}} links to relative .md links with --relative-links', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true, '--relative-links' => true])->assertSuccessful();

    expect(file_get_contents($this->base.'/resources/docs/2.2/overview.md'))->toContain('See an [example](example-project-tracking.md).')
        ->and(file_get_contents($this->base.'/resources/docs/2.2/authorization.md'))->toContain('(configurations.md#settings)');
});

it('keeps {{version}} links by default, since v3 still fills them in', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true])->assertSuccessful();

    expect(file_get_contents($this->base.'/resources/docs/2.2/overview.md'))->toContain('(/docs/{{version}}/example-project-tracking)');
});

it('stops when the app already runs on LaRecipe 3', function () {
    $this->artisan('larecipe:upgrade', ['--apply' => true])->assertSuccessful();

    $this->artisan('larecipe:upgrade')->expectsOutputToContain('already uses the LaRecipe 3 config')->assertFailed();
});

it('refuses to move the docs into a folder that already has files', function () {
    mkdir($this->base.'/docs');
    file_put_contents($this->base.'/docs/keep.md', '# Keep');

    $this->artisan('larecipe:upgrade', ['--move-to' => 'docs'])->expectsOutputToContain('already has files')->assertFailed();
});
