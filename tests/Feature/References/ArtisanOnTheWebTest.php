<?php

use Illuminate\Console\Application as Artisan;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

/*
 * <Artisan> reads the console kernel's command list, on web requests too. A
 * web request boots the providers outside the console, so LaRecipe's own
 * commands must be registered there as well, or docs about them break.
 */

beforeEach(function () {
    $this->onTheWeb = fn () => [$_ENV['APP_RUNNING_IN_CONSOLE'] = 'false', $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false', putenv('APP_RUNNING_IN_CONSOLE=false')];
    ($this->onTheWeb)();
    Artisan::forgetBootstrappers();
    $this->refreshApplication();
    $this->setUpTraits();
});

afterEach(function () {
    unset($_ENV['APP_RUNNING_IN_CONSOLE'], $_SERVER['APP_RUNNING_IN_CONSOLE']);
    putenv('APP_RUNNING_IN_CONSOLE');
});

it('boots the providers as a web request would', function () {
    expect(app()->runningInConsole())->toBeFalse();
});

it('resolves <Artisan command="larecipe:validate" /> during an HTTP request', function () {
    $root = Fixtures::temporaryDirectory('artisan-web');
    file_put_contents($root.'/index.md', "---\ntitle: Commands\ndescription: Commands.\n---\n\n# Commands\n\n<Artisan command=\"larecipe:validate\" />\n");

    TrackG::useSets(['cli' => [
        'route' => ['prefix' => 'cli', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    $this->get('/cli')->assertOk()
        ->assertSee('data-kind="artisan"', false)
        ->assertSee('php artisan larecipe:validate')
        ->assertDontSee('references.missing');

    $this->get('/cli/index.md')->assertOk()->assertSee("```bash\nphp artisan larecipe:validate", false);
});
