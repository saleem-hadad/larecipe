<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::useSets([
        'gated' => TrackG::set('gated', 'gated'),
        'staff' => TrackG::set('gated', 'staff', 'auth'),
        'atlas' => TrackG::set('i18n', 'atlas'),
    ]);

    $this->out = sys_get_temp_dir().'/larecipe-export-'.bin2hex(random_bytes(4));
});

afterEach(function () {
    TrackG::remove($this->out);
});

function exportSite(array $options): string
{
    Artisan::call('larecipe:export', $options);

    return Artisan::output();
}

it('writes every public page as HTML plus its Markdown twin', function () {
    exportSite(['--out' => $this->out, '--set' => ['docs'], '--base-url' => 'https://docs.acme.dev']);

    expect($this->out.'/docs/installation/index.html')->toBeFile()
        ->and($this->out.'/docs/installation.md')->toBeFile()
        ->and($this->out.'/docs/guides/webhooks/index.html')->toBeFile()
        ->and($this->out.'/docs/index.html')->toBeFile()
        ->and($this->out.'/docs/index.md')->toBeFile()
        ->and($this->out.'/docs/404.html')->toBeFile();

    $html = file_get_contents($this->out.'/docs/installation/index.html');

    expect($html)
        ->toContain('<html data-lr-static lang="en"')
        ->toContain('<link rel="canonical" href="https://docs.acme.dev/docs/installation">')
        ->toContain('href="/docs/guides/webhooks/#retries"');

    // Code is highlighted into spans, so compare the text.
    expect(strip_tags($html))->toContain('composer require acme/acme');

    expect(file_get_contents($this->out.'/docs/installation.md'))->toContain('url: \'https://docs.acme.dev/docs/installation\'');
});

it('keeps the docs\' own assets and points pages at them', function () {
    exportSite(['--out' => $this->out, '--set' => ['docs']]);

    expect($this->out.'/docs/_assets/current/flow.png')->toBeFile()
        ->and(md5_file($this->out.'/docs/_assets/current/flow.png'))->toBe(md5_file(TrackG::fixture('../docs/_assets/flow.png')))
        ->and(file_get_contents($this->out.'/docs/guides/webhooks/index.html'))->toContain('src="/docs/_assets/current/flow.png"');
});

it('leaves out private sets, versions, pages and gated blocks, and says so', function () {
    $output = exportSite(['--out' => $this->out, '--set' => ['docs', 'gated', 'staff']]);

    expect($this->out.'/docs/secret/index.html')->not->toBeFile()
        ->and($this->out.'/docs/secret.md')->not->toBeFile()
        ->and($this->out.'/gated/next')->not->toBeDirectory()
        ->and($this->out.'/gated/2.x/roadmap/index.html')->not->toBeFile()
        ->and($this->out.'/staff')->not->toBeDirectory();

    $guest = file_get_contents($this->out.'/gated/2.x/index.html').file_get_contents($this->out.'/gated/2.x/index.md');

    expect($guest)->toContain('GUEST-ONLY')->not->toContain('EXPORT-SECRET')->not->toContain('MANAGER-SECRET')->not->toContain('ROADMAP-SECRET');

    expect($output)
        ->toContain('The staff set: it is private')
        ->toContain('Version next of the gated set: it is private.')
        ->toContain('1 private page of the docs set.')
        ->toContain('1 private page of the gated set.')
        ->toContain('Ask AI')
        ->toContain('The MCP server')
        ->toContain('Page feedback');
});

it('turns a redirecting set root into a static redirect page', function () {
    exportSite(['--out' => $this->out, '--set' => ['gated']]);

    expect(file_get_contents($this->out.'/gated/index.html'))
        ->toContain('<meta http-equiv="refresh" content="0; url=/gated/2.x/">');
});

it('exports every locale, fallback pages included', function () {
    exportSite(['--out' => $this->out, '--set' => ['atlas']]);

    expect($this->out.'/atlas/ar/1.x/index.html')->toBeFile()
        ->and($this->out.'/atlas/ar/1.x/notes/index.html')->toBeFile()
        ->and($this->out.'/atlas/fr/1.x/install/index.html')->toBeFile()
        ->and(file_get_contents($this->out.'/atlas/ar/1.x/guides/deploy/index.html'))
        ->toContain('dir="rtl"')
        ->toContain('ENGLISH-ONLY')
        ->toContain('href="/atlas/ar/1.x/install/"');
});

it('serves from a sub-path when the base URL has one', function () {
    exportSite(['--out' => $this->out, '--set' => ['docs'], '--base-url' => 'https://acme.github.io/handbook']);

    $html = file_get_contents($this->out.'/docs/installation/index.html');

    expect($html)
        ->toContain('<link rel="canonical" href="https://acme.github.io/handbook/docs/installation">')
        ->toContain('href="/handbook/docs/guides/webhooks/#retries"')
        ->toContain('"path_url":"/handbook/docs/installation/"');
});

it('resolves live references at export time', function () {
    TrackG::bootFixtureApp();
    TrackG::useSets(['shop' => TrackG::set('references', 'shop')]);

    exportSite(['--out' => $this->out, '--set' => ['shop']]);

    expect(file_get_contents($this->out.'/shop/index.html'))->toContain('<code>POST /orders</code>')
        ->and($this->out.'/shop/broken/index.html')->toBeFile();
});

it('writes the not-found page for a versioned set too', function () {
    exportSite(['--out' => $this->out, '--set' => ['gated']]);

    expect($this->out.'/gated/404.html')->toBeFile()
        ->and(file_get_contents($this->out.'/gated/404.html'))->toContain('<meta name="robots" content="noindex">');
});

it('copies the files from public/ the pages reference, and nothing else', function () {
    $public = Fixtures::temporaryDirectory('public');
    $files = [
        'brand/mark.svg' => '<svg id="mark"/>',
        'brand/mark-dark.svg' => '<svg id="mark-dark"/>',
        'favicon.svg' => '<svg id="favicon"/>',
        'evil.php' => '<?php echo "secret";',
        'index.php' => '<?php // front controller',
        'secret.txt' => 'not referenced',
        'build/larecipe/manifest.json' => json_encode(['extension.tsx' => ['file' => 'extension-abc.js', 'isEntry' => true, 'css' => ['extension-abc.css']], '_lazy.js' => ['file' => 'lazy-def.js']]),
        'build/larecipe/extension-abc.js' => 'import("./lazy-def.js")',
        'build/larecipe/extension-abc.css' => '.x{}',
        'build/larecipe/lazy-def.js' => 'export default 1',
    ];

    foreach ($files as $path => $contents) {
        @mkdir(dirname($public.'/'.$path), 0755, true);
        file_put_contents($public.'/'.$path, $contents);
    }

    $docs = Fixtures::temporaryDirectory('branded');
    mkdir($docs.'/3.x', 0755, true);
    file_put_contents($docs.'/3.x/index.md', "# Home\n\n![php](/evil.php) [secret](/index.php)\n");
    file_put_contents($docs.'/larecipe.json', json_encode([
        'name' => 'Branded',
        'logo' => ['light' => '/brand/mark.svg', 'dark' => '/brand/mark-dark.svg'],
        'favicon' => '/favicon.svg',
        'versions' => [['id' => '3.x', 'default' => true]],
    ]));

    $this->app->usePublicPath($public);
    $this->app->forgetInstance(Assets::class);
    TrackG::useSets(['branded' => ['route' => ['prefix' => '', 'middleware' => ['web']], 'source' => ['driver' => 'repository', 'path' => $docs], 'access' => ['mode' => 'public']]]);

    exportSite(['--out' => $this->out, '--set' => ['branded']]);

    expect(file_get_contents($this->out.'/brand/mark.svg'))->toBe('<svg id="mark"/>')
        ->and(file_get_contents($this->out.'/brand/mark-dark.svg'))->toBe('<svg id="mark-dark"/>')
        ->and(file_get_contents($this->out.'/favicon.svg'))->toBe('<svg id="favicon"/>')
        ->and($this->out.'/build/larecipe/extension-abc.js')->toBeFile()
        ->and($this->out.'/build/larecipe/extension-abc.css')->toBeFile()
        ->and($this->out.'/build/larecipe/lazy-def.js')->toBeFile()
        ->and($this->out.'/evil.php')->not->toBeFile()
        ->and($this->out.'/index.php')->not->toBeFile()
        ->and($this->out.'/secret.txt')->not->toBeFile()
        ->and($this->out.'/404.html')->toBeFile();

    TrackG::remove($public);
    TrackG::remove($docs);
});

it('refuses a set it does not know', function () {
    $this->artisan('larecipe:export', ['--out' => $this->out, '--set' => ['nope']])->assertFailed();
});

it('does not offer feedback, Ask AI or MCP, which a static host cannot serve', function () {
    $live = $this->get('/docs/installation')->getContent();

    exportSite(['--out' => $this->out, '--set' => ['docs']]);

    $html = file_get_contents($this->out.'/docs/installation/index.html');
    $boot = fn (string $page) => preg_match('#"features":\{[^}]*\}#', $page, $match) ? $match[0] : '';

    expect($live)->toContain('class="lr-feedback')->toContain('>Connect MCP<')
        ->and($html)->not->toContain('class="lr-feedback')->not->toContain('>Connect MCP<')
        ->and($boot($html))->toContain('"ask":false')->toContain('"feedback":false');
});
