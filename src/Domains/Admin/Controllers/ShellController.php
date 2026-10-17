<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use LaRecipe\Domains\Admin\Actions\BuildBootPayloadAction;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;

/**
 * The admin shell: a small HTML page that mounts the admin React entry and
 * hands it the boot payload. Every admin URL serves it (the app routes on
 * the client), and it is never cached or indexed.
 */
final class ShellController
{
    public function __construct(
        private readonly SetFromRequest $sets,
        private readonly BuildBootPayloadAction $boot,
        private readonly Assets $assets,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->sets->set($request);

        $html = View::make('larecipe::admin', [
            'set' => $set,
            'boot' => $this->boot->execute($set, $request->user()),
            'assets' => $this->assets,
        ])->render();

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    /** An API path that does not exist answers JSON, so the app can say so instead of parsing HTML. */
    public function missing(): JsonResponse
    {
        return new JsonResponse(['message' => 'There is no such admin endpoint.'], 404);
    }
}
