<?php

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The host route behind the example Subscribe component (ARCHITECTURE.md 9.5).
 * The React form posts JSON through request(); the server fallback posts a
 * plain form and is redirected to a confirmation page.
 */
class SubscribeController
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'list' => ['nullable', 'string', 'max:64'],
        ]);

        return $request->expectsJson()
            ? response()->json(['subscribed' => true, 'email' => $data['email']])
            : redirect('/newsletter?subscribed=1');
    }
}
