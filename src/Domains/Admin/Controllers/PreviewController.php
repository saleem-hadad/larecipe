<?php

namespace LaRecipe\Domains\Admin\Controllers;

use Illuminate\Http\JsonResponse;
use LaRecipe\Domains\Admin\Actions\RenderPreviewAction;
use LaRecipe\Domains\Admin\Requests\PreviewRequest;
use LaRecipe\Domains\Markdown\DTOs\TocItem;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\LaRecipe;

/**
 * The editor's two live companions: the preview, rendered by the server
 * renderer exactly as a reader receives it, and the inline validation
 * issues, from the same rules every write runs. Neither saves anything.
 */
final class PreviewController
{
    public function __construct(
        private readonly RenderPreviewAction $render,
        private readonly ValidatePageAction $validator,
    ) {}

    public function preview(PreviewRequest $request): JsonResponse
    {
        $page = $request->page();
        $rendered = $this->render->execute($request->docSet(), $page);

        return new JsonResponse([
            'title' => $page->title,
            'description' => $page->description,
            // Component fallbacks carry a CSRF placeholder, filled per response like the reader's page.
            'html' => str_replace(LaRecipe::CSRF_PLACEHOLDER, e(csrf_token() ?? ''), $rendered->html),
            'toc' => array_map(fn (TocItem $item) => $item->toArray(), $rendered->toc),
            'components' => $rendered->components,
            'issues' => $rendered->issues,
        ]);
    }

    public function validate(PreviewRequest $request): JsonResponse
    {
        return new JsonResponse($this->validator->execute($request->page())->toArray());
    }
}
