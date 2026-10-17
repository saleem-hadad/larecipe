<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Access\Actions\IssueShareLinkAction;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::useSets([
        'gated' => TrackG::set('gated', 'gated'),
        'staff' => TrackG::set('gated', 'staff', 'auth'),
    ]);
});

function shareUrl(string $page, string $expires = '7d'): string
{
    Artisan::call('larecipe:share', ['page' => $page, '--expires' => $expires]);

    preg_match('#http\S+share=\S+#', Artisan::output(), $matches);

    return $matches[0] ?? '';
}

it('issues a link that opens one private page in every representation', function () {
    $url = shareUrl('gated/2.x/roadmap');
    [$path, $query] = explode('?', parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY));

    expect($path)->toBe('/gated/2.x/roadmap')->and($query)->toStartWith('share=');

    $this->get('/gated/2.x/roadmap')->assertNotFound();

    $this->get("{$path}?{$query}")->assertOk()->assertSee('ROADMAP-SECRET');
    $this->get("{$path}.md?{$query}")->assertOk()->assertSee('ROADMAP-SECRET');
    $this->get("{$path}?{$query}", ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->assertJsonPath('page.title', 'Roadmap');
});

it('marks shared responses private, unindexed and never referred', function () {
    $url = shareUrl('gated/2.x/roadmap');

    foreach (['', '.md'] as $suffix) {
        $response = $this->get(str_replace('/roadmap?', "/roadmap{$suffix}?", $url))->assertOk();

        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    }
});

it('opens nothing but the page it was issued for', function () {
    $query = parse_url(shareUrl('gated/2.x/roadmap'), PHP_URL_QUERY);

    $this->get("/gated/next?{$query}")->assertNotFound();
    $this->get("/gated/2.x/plans?{$query}")->assertOk();
    // The same page in another set is another page: sign-in is still required there.
    $this->get("/staff/2.x/roadmap?{$query}", ['Accept' => 'application/vnd.larecipe.page+json'])->assertUnauthorized();
});

it('stops working when it expires or is tampered with', function () {
    $url = shareUrl('gated/2.x/roadmap', '2h');

    $this->get($url)->assertOk();
    $this->get(substr($url, 0, -1).(str_ends_with($url, 'A') ? 'B' : 'A'))->assertNotFound();

    $this->travel(3)->hours();
    $this->get($url)->assertNotFound();
});

it('opens a page in a private version and in a set that needs sign-in', function () {
    $this->get(shareUrl('gated/next'))->assertOk()->assertSee('NEXT-SECRET');
    $this->get(shareUrl('staff/2.x/plans'))->assertOk()->assertSee('PLANS-TEXT');
});

it('keeps the token across the redirect to the default version', function () {
    $link = app(IssueShareLinkAction::class)->execute(new PageRef('gated', '2.x', 'en', 'roadmap'));

    $location = $this->get('/gated/roadmap?share='.$link->token)->assertRedirect()->headers->get('Location');

    expect($location)->toContain('/gated/2.x/roadmap?share='.$link->token);
    $this->get($location)->assertOk()->assertSee('ROADMAP-SECRET');
});

it('refuses a lifetime it cannot read', function () {
    $this->artisan('larecipe:share', ['page' => 'gated/2.x/roadmap', '--expires' => 'soon'])->assertFailed();
});
