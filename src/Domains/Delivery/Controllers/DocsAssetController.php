<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the images and files that live with the docs (docs/{version}/_assets).
 */
final class DocsAssetController
{
    public function __construct(
        private readonly \LaRecipe\Domains\Delivery\Support\SetFromRequest $current,
        private readonly SetRegistry $sets,
        private readonly SourceResolver $sources,
        private readonly PageAccess $access,
    ) {}

    public function __invoke(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $version = (string) $request->route('version');
        $file = (string) $request->route('file');
        $set = $this->current->set($request);

        if (! $this->access->canEnter($set) || ($set->version($version) === null && $version !== 'current')) {
            abort(404);
        }

        $asset = $this->sources->for($set->key)->asset($version, $file);

        if ($asset === null) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $asset->mime,
            'Cache-Control' => $this->access->isPublic($set) ? 'public, max-age=3600' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($asset->mime === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return $asset->absolutePath !== null
            ? new BinaryFileResponse($asset->absolutePath, 200, $headers)
            : new Response($asset->contents(), 200, $headers);
    }
}
