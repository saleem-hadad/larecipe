<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaRecipe\Domains\Sync\Exceptions\SyncRefused;
use LaRecipe\Domains\Sync\Laravel\McpRemoteAuthorTools;

it('needs a token before it connects', function () {
    Http::fake();

    expect(fn () => (new McpRemoteAuthorTools(null))->tools('https://acme.test/kb/mcp'))
        ->toThrow(SyncRefused::class, 'Set LARECIPE_SYNC_TOKEN');

    Http::assertNothingSent();
});

it('sends the token as a bearer token and explains a refusal', function () {
    Http::fake(['acme.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    expect(fn () => (new McpRemoteAuthorTools('remote-token'))->tools('https://acme.test/kb/mcp'))
        ->toThrow(SyncRefused::class, 'refused the token');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://acme.test/kb/mcp'
        && $request->hasHeader('Authorization', 'Bearer remote-token'));
});

it('explains an endpoint it cannot reach', function () {
    Http::fake(['acme.test/*' => Http::response('Server Error', 500)]);

    expect(fn () => (new McpRemoteAuthorTools('remote-token'))->tools('https://acme.test/kb/mcp'))
        ->toThrow(SyncRefused::class, 'could not reach the MCP endpoint [https://acme.test/kb/mcp]');
});
