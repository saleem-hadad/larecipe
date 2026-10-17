<?php

/*
 * Components, alerts, highlighting and snippet includes through real page
 * requests, in both representations, plus the render cache rules they need.
 */

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Tests\Feature\Markdown\UsesGuideDocs;
use LaRecipe\Tests\TestCase;

uses(UsesGuideDocs::class);

it('serves components as server HTML', function () {
    $this->get('/guide')
        ->assertOk()
        ->assertSee('<div class="lr-card-group" data-cols="2"><a class="lr-card" href="/guide/installation">', false)
        ->assertSee('<figure class="lr-video lr-video-placeholder">', false)
        ->assertSee('Demo: Acme in two minutes');
});

it('serves highlighted code and alerts', function () {
    $this->get('/guide/installation')
        ->assertOk()
        ->assertSee('<figure class="lr-code" data-language="bash">', false)
        ->assertSee('<aside class="lr-callout" data-type="tip" role="note">', false)
        ->assertSeeText('composer require acme/acme');
});

it('includes real code from the snippet root', function () {
    $this->get('/guide/billing/refunds')
        ->assertOk()
        ->assertSee('data-title="app/Billing/Invoice.php"', false)
        ->assertSeeText('public function refund(int $amount): Refund');
});

it('serves the agent Markdown twin from the same AST', function () {
    $markdown = $this->get('/guide/billing/refunds.md')->assertOk()->getContent();

    expect($markdown)->toContain("title: Refunds\n")
        ->and($markdown)->toContain("# Refunds\n")
        ->and($markdown)->toContain('[webhooks](http://localhost/guide/billing/webhooks.md#retries)')
        ->and($markdown)->toContain("```php title=\"app/Billing/Invoice.php\"\npublic function refund(int \$amount): Refund")
        ->and($markdown)->not->toContain('<<<');

    $intro = $this->get('/guide/index.md')->assertOk()->getContent();

    expect($intro)->toContain('- [Installation](http://localhost/guide/installation.md): Add Acme to an app.')
        ->and($intro)->toContain('*Video coming soon: Demo: Acme in two minutes*')
        ->and($this->get('/guide/installation.md')->getContent())->toContain("> [!TIP]\n> Run the migrations before the first deploy.");
});

it('caches renders with warnings and re-renders when an included file changes', function () {
    $root = sys_get_temp_dir().'/larecipe-snippets-'.uniqid();
    mkdir($root.'/app', 0777, true);
    file_put_contents($root.'/app/Hello.php', "<?php\n// #region greet\necho 'hello';\n// #endregion\n");

    $page = Page::make(new PageRef('docs', 'current', 'en', 'cached'), new Frontmatter(['title' => 'Cached']), "<<< @/app/Hello.php#greet\n\n<Video />");
    $cache = app(RenderCache::class);
    $renders = 0;
    $render = function () use ($page, $root, &$renders) {
        $renders++;

        return app(RenderPageAction::class)->execute($page, null, ['snippets_root' => $root]);
    };

    $first = $cache->remember($page, 'test', $render);
    $cache->remember($page, 'test', $render);

    expect($renders)->toBe(1)
        ->and($first->issues[0]['rule'])->toBe('media.placeholder')
        ->and(strip_tags($first->html))->toContain("echo 'hello';");

    file_put_contents($root.'/app/Hello.php', "<?php\n// #region greet\necho 'goodbye';\n// #endregion\n");
    $changed = $cache->remember($page, 'test', $render);

    expect($renders)->toBe(2)->and(strip_tags($changed->html))->toContain("echo 'goodbye';");

    array_map('unlink', [$root.'/app/Hello.php']);
    rmdir($root.'/app');
    rmdir($root);
});

it('does not cache renders with errors', function () {
    $page = Page::make(new PageRef('docs', 'current', 'en', 'broken'), new Frontmatter(['title' => 'Broken']), '<<< @/app/Missing.php');
    $renders = 0;
    $render = function () use ($page, &$renders) {
        $renders++;

        return app(RenderPageAction::class)->execute($page, null, ['snippets_root' => TestCase::fixture('track-a/app')]);
    };

    app(RenderCache::class)->remember($page, 'test', $render);
    app(RenderCache::class)->remember($page, 'test', $render);

    expect($renders)->toBe(2);
});
