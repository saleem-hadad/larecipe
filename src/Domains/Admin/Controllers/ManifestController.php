<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\SaveSettingsAction;
use LaRecipe\Domains\Admin\DTOs\SettingsChange;
use LaRecipe\Domains\Admin\Laravel\AdminResponses;
use LaRecipe\Domains\Admin\Requests\ScopeRequest;
use LaRecipe\Domains\Admin\Requests\UpdateManifestRequest;
use LaRecipe\Domains\Admin\Support\AdminJson;
use LaRecipe\Domains\Sources\Actions\HashContentAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * The set settings screen: larecipe.json as it is in production (name, logo,
 * theme, versions, locales, navbar, redirects, the agent description), and
 * saving it back.
 */
final class ManifestController
{
    public function __construct(
        private readonly SourceResolver $sources,
        private readonly HashContentAction $hash,
        private readonly SaveSettingsAction $settings,
        private readonly AdminResponses $responses,
    ) {}

    public function show(ScopeRequest $request): JsonResponse
    {
        $manifest = $this->sources->for($request->docSet()->key)->manifest();

        return new JsonResponse([
            'manifest' => AdminJson::map($manifest?->toArray() ?? []),
            'exists' => $manifest !== null,
            'content_hash' => $this->hash->execute($manifest),
        ]);
    }

    public function update(UpdateManifestRequest $request): JsonResponse
    {
        return $this->responses->guard(fn () => $this->responses->write($this->settings->execute(new SettingsChange(
            set: $request->docSet()->key,
            manifest: $request->manifest(),
            author: $request->author(),
            summary: $request->summary(),
            basedOn: $request->input('based_on'),
            mode: $request->mode(),
        ))));
    }
}
