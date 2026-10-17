<?php

it('serves a page as complete server HTML', function () {
    $this->get('/docs/installation')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<h1 class="lr-title">Installation</h1>', false)
        ->assertSee('data-lr-article', false)
        ->assertSeeText('composer require acme/acme')
        ->assertSee('id="lr-boot"', false);
});

it('renders the landing page at the set root', function () {
    $this->get('/docs')->assertOk()->assertSee('Acme handles');
});

it('serves the markdown twin by suffix and by Accept header', function () {
    $this->get('/docs/installation.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('title: Installation')
        ->assertSee('composer require acme/acme');

    $this->get('/docs/installation', ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
});

it('serves the client navigation payload', function () {
    $this->get('/docs/installation', ['Accept' => 'application/vnd.larecipe.page+json'])
        ->assertOk()
        ->assertJsonPath('page.title', 'Installation')
        ->assertJsonStructure(['page' => ['html', 'toc'], 'navigation']);
});

it('sends negotiation and agent discovery headers', function () {
    $response = $this->get('/docs/installation');

    expect($response->headers->get('Vary'))->toContain('Accept')
        ->and($response->headers->get('Link'))->toContain('rel="alternate"; type="text/markdown"')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('rewrites relative markdown links and images to reader URLs', function () {
    $this->get('/docs/guides/webhooks')
        ->assertOk()
        ->assertSee('href="/docs/installation"', false)
        ->assertSee('src="/docs/_assets/current/flow.png"', false);

    $this->get('/docs/_assets/current/flow.png')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('builds the table of contents with stable heading ids', function () {
    $this->get('/docs/installation', ['Accept' => 'application/vnd.larecipe.page+json'])
        ->assertJsonPath('page.toc.0.id', 'requirements')
        ->assertJsonPath('page.toc.1.id', 'install');
});

it('derives navigation from folders and frontmatter', function () {
    $nav = $this->get('/docs/installation', ['Accept' => 'application/vnd.larecipe.page+json'])->json('navigation.0.items');

    expect(collect($nav)->pluck('title')->filter()->values()->all())->toBe(['Introduction', 'Installation'])
        ->and(collect($nav)->firstWhere('type', 'group')['label'])->toBe('Guides');
});

it('hides private pages from guests everywhere with a real 404', function () {
    $this->get('/docs/secret')->assertNotFound();
    $this->get('/docs/secret.md')->assertNotFound()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $nav = $this->get('/docs', ['Accept' => 'application/vnd.larecipe.page+json'])->json('navigation.0.items');
    expect(collect($nav)->pluck('path')->all())->not->toContain('secret');
});

it('renders component tags as server HTML without allowing raw HTML', function () {
    $this->get('/docs')
        ->assertSee('class="lr-callout" data-type="tip"', false)
        ->assertSee('Every page is also available as Markdown.');
});

it('redirects unversioned URLs of a versioned set to the default version', function () {
    expect(parse_url($this->get('/handbook')->assertRedirect()->headers->get('Location'), PHP_URL_PATH))->toBe('/handbook/2.x');
    expect(parse_url($this->get('/handbook/deploys')->assertRedirect()->headers->get('Location'), PHP_URL_PATH))->toBe('/handbook/2.x/deploys');
});

it('serves versions and navigation.json', function () {
    $this->get('/handbook/2.x/deploys')->assertOk()->assertSee('Ship it.');
    $this->get('/handbook/1.x')->assertOk()->assertSee('The old handbook.');

    $nav = $this->get('/handbook/2.x', ['Accept' => 'application/vnd.larecipe.page+json'])->json('navigation.0.items');
    expect($nav[0]['label'])->toBe('Start')
        ->and($nav[1]['href'])->toBe('https://status.example.com');
});

it('serves another locale and falls back to the default locale with a notice', function () {
    $this->get('/handbook/ar/2.x')->assertOk()->assertSee('dir="rtl"', false)->assertSee('الدليل الحالي');
    $this->get('/handbook/ar/2.x/deploys')->assertOk()->assertSee('not translated yet')->assertSee('Ship it.');
});

it('returns a real 404 for missing pages in every representation', function () {
    $this->get('/docs/nope')->assertNotFound();
    $this->get('/docs/nope', ['Accept' => 'application/vnd.larecipe.page+json'])->assertNotFound();
});
