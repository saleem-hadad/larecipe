<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\LaRecipe;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

/*
 * ARCHITECTURE.md 9.1: a theme.css in the docs root, listed under assets.css in
 * larecipe.json, loads after LaRecipe's own CSS for small overrides.
 */

function themedSet(array $css = ['theme.css'], bool $writeFile = true): string
{
    $root = Fixtures::temporaryDirectory('themed');

    foreach (['3.x', '2.x'] as $version) {
        mkdir($root.'/'.$version, 0755, true);
        file_put_contents($root.'/'.$version.'/index.md', "---\ntitle: Home\ndescription: Home.\n---\n\n# Home {$version}\n");
    }

    file_put_contents($root.'/larecipe.json', json_encode(['name' => 'Themed', 'versions' => [['id' => '3.x', 'default' => true], ['id' => '2.x']], 'assets' => ['css' => $css]]));

    if ($writeFile) {
        file_put_contents($root.'/theme.css', ':root { --lr-accent: #e11d48; }');
    }

    TrackG::useSets(['themed' => [
        'route' => ['prefix' => 'themed', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    return $root;
}

function themeVersion(): string
{
    return substr(hash('sha256', ':root { --lr-accent: #e11d48; }'), 0, 12);
}

it('links theme.css after LaRecipe\'s own CSS, fingerprinted by its content', function () {
    themedSet();
    $dist = Fixtures::temporaryDirectory('dist');
    mkdir($dist.'/.vite', 0755, true);
    file_put_contents($dist.'/.vite/manifest.json', json_encode(['src/app/main.tsx' => ['file' => 'assets/app-abc.js', 'name' => 'app', 'isEntry' => true, 'css' => ['assets/app-abc.css']]]));
    $this->app->instance(Assets::class, new Assets(new LaRecipe(app()), null, null, $dist));

    $html = $this->get('/themed/2.x')->assertOk()->getContent();
    $link = '<link rel="stylesheet" href="/themed/_assets/2.x/theme.css?v='.themeVersion().'">';

    expect($html)->toContain($link)
        ->and(strpos($html, $link))->toBeGreaterThan((int) strpos($html, 'app-abc.css'))
        ->and(strpos($html, $link))->toBeLessThan((int) strpos($html, '</head>'));
});

it('serves theme.css from the docs root in every version, cached for good when fingerprinted', function () {
    themedSet();

    $this->get('/themed/_assets/3.x/theme.css')->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8')
        ->assertHeader('Cache-Control', 'max-age=3600, public');

    $response = $this->get('/themed/_assets/2.x/theme.css?v='.themeVersion())->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000')->toContain('immutable')->toContain('public')
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe(':root { --lr-accent: #e11d48; }');
});

it('serves only the stylesheets larecipe.json lists from the docs root', function () {
    $root = themedSet(['theme.css']);
    file_put_contents($root.'/other.css', 'body { color: red; }');

    $this->get('/themed/_assets/3.x/other.css')->assertNotFound();
});

it('links nothing for a listed file that does not exist, and larecipe:validate reports it', function () {
    themedSet(['theme.css', 'missing.css', '../outside.css']);

    expect($this->get('/themed/3.x')->assertOk()->getContent())
        ->toContain('/themed/_assets/3.x/theme.css?v=')
        ->not->toContain('missing.css')
        ->not->toContain('outside.css');

    $issues = collect(app(ValidateDocsAction::class)->execute('themed')->issues)
        ->filter(fn (ValidationIssue $issue) => $issue->rule === 'assets.missing')
        ->map(fn (ValidationIssue $issue) => $issue->message)
        ->values()->all();

    expect($issues)->toBe([
        'larecipe.json lists missing.css under assets.css, but there is no such file next to larecipe.json.',
        'larecipe.json lists ../outside.css under assets.css, but there is no such file next to larecipe.json.',
    ]);
});

it('exports theme.css with the static site', function () {
    themedSet();
    $out = Fixtures::temporaryDirectory('themed-export');

    Artisan::call('larecipe:export', ['--out' => $out, '--set' => ['themed']]);

    expect($out.'/themed/_assets/3.x/theme.css')->toBeFile()
        ->and(file_get_contents($out.'/themed/3.x/index.html'))->toContain('href="/themed/_assets/3.x/theme.css?v='.themeVersion().'"');
});
