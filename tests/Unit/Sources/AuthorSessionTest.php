<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Mcp\AuthorSession;

it('treats a console call without an HTTP route as the local stdio server', function () {
    expect(app(AuthorSession::class)->isLocal())->toBeTrue()
        ->and(app(AuthorSession::class)->channel())->toBe(Channel::PullRequest);
});

it('treats a call that serves an HTTP route as the web server, even in a console process (Octane)', function () {
    $request = Request::create('/docs/mcp', 'POST');
    $request->setRouteResolver(fn () => new Route('POST', 'docs/mcp', fn () => null));
    $this->app->instance('request', $request);

    expect(app(AuthorSession::class)->isLocal())->toBeFalse()
        ->and(app(AuthorSession::class)->channel())->toBe(Channel::RemoteAgent);
});
