<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Support\DocsStylesheets;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the images and files that live with the docs (docs/{version}/_assets,
 * or next to the pages that use them).
 *
 * The same access rules as the pages (the Same-Rules Rule): a private version's
 * files only for readers who may see that version, and "current" is the default
 * version, never a way around it. Which paths are servable at all (no page
 * sources, manifest, navigation or dot files) is the source's call.
 */
final class DocsAssetController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly SetRegistry $sets,
        private readonly SourceResolver $sources,
        private readonly PageAccess $access,
    ) {}

    public function __invoke(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $set = $this->current->set($request);
        $version = (string) $request->route('version');
        $version = $version === Version::IMPLICIT ? $set->defaultVersion()->id : $version;

        if (! $this->access->canViewVersion($set, $version)) {
            abort(404);
        }

        $asset = $this->sources->for($set->key)->asset($version, (string) $request->route('file'));

        if ($asset === null) {
            abort(404);
        }

        $public = $this->access->isPublic($set) && $set->version($version)?->visibility !== Visibility::Private;

        // A URL fingerprinted with this exact content (a stylesheet from assets.css) never changes.
        $fingerprinted = $asset->hash !== null && $request->query('v') === DocsStylesheets::fingerprint($asset->hash);

        $headers = [
            'Content-Type' => $asset->mime,
            'Cache-Control' => ($public ? 'public' : 'private').($fingerprinted ? ', max-age=31536000, immutable' : ', max-age=3600'),
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($asset->mime === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return $asset->absolutePath !== null
            ? new BinaryFileResponse($asset->absolutePath, 200, $headers, public: $public)
            : new Response($asset->contents(), 200, $headers);
    }
}
