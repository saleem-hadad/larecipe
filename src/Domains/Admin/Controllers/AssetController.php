<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\ListAssetsAction;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Queries\AssetReferencesQuery;
use LaRecipe\Domains\Admin\Requests\DeleteAssetRequest;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Requests\UploadAssetRequest;
use LaRecipe\Domains\Sources\Actions\DeleteAssetAction;
use LaRecipe\Domains\Sources\Actions\WriteAssetAction;
use LaRecipe\Domains\Sources\DTOs\AssetUpload;

/**
 * Images and files for the docs, on the set's configured disk. Uploads go
 * through WriteAssetAction (which refreshes the cache fingerprint); a
 * deletion is refused while a page still uses the file, unless the person
 * confirms they want it gone anyway.
 */
final class AssetController
{
    public function __construct(
        private readonly ListAssetsAction $assets,
        private readonly AssetReferencesQuery $references,
        private readonly WriteAssetAction $write,
        private readonly DeleteAssetAction $delete,
        private readonly AdminResponses $responses,
    ) {}

    public function index(ScopeRequest $request): JsonResponse
    {
        return new JsonResponse([
            'version' => $request->version(),
            'assets' => $this->assets->execute($request->docSet(), $request->version()),
        ]);
    }

    public function store(UploadAssetRequest $request): JsonResponse
    {
        $set = $request->docSet();
        $path = $request->assetPath();

        return $this->responses->guard(function () use ($request, $set, $path) {
            $this->write->execute(new AssetUpload($set->key, $request->version(), $path, (string) $request->upload()->get()));
            $listed = array_values(array_filter($this->assets->execute($set, $request->version()), fn (array $asset) => $asset['path'] === $path));

            return new JsonResponse(['asset' => $listed[0] ?? ['path' => $path]], 201);
        });
    }

    public function destroy(DeleteAssetRequest $request): JsonResponse
    {
        $set = $request->docSet();
        $path = ltrim((string) $request->input('path'), '/');
        $references = $this->references->get($set, $request->version(), [$path])[$path] ?? [];

        if ($references !== [] && ! $request->boolean('force')) {
            return new JsonResponse([
                'message' => 'Still used by '.implode(', ', $references).'. Remove it from those pages first, or delete it anyway.',
                'references' => $references,
            ], 409);
        }

        $this->delete->execute($set->key, $request->version(), $path);

        return new JsonResponse(['deleted' => $path]);
    }
}
