<?php

namespace LaRecipe\Domains\Delivery\Support;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Access\Services\PageAccess;

/**
 * A real 404 in every representation (no soft 404s). A page that exists but is
 * hidden from the caller gets the same answer, so access rules never confirm
 * that a private page exists.
 */
final class NotFoundResponder
{
    /** @var (Closure(DocSet, DocsRequest): list<array{title: string, url: string, description: ?string}>)|null */
    private ?Closure $suggestions = null;

    public function __construct(
        private readonly DocsUrls $urls,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly Assets $assets,
    ) {}

    /** The Search domain plugs in ranked suggestions for similar pages. */
    public function suggestUsing(Closure $suggestions): void
    {
        $this->suggestions = $suggestions;
    }

    public function respond(DocSet $set, DocsRequest $docs, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $suggestions = $this->suggestions !== null && $this->access->canEnter($set) ? ($this->suggestions)($set, $docs) : [];

        if ($docs->isMarkdown()) {
            $lines = ["# Page not found", '', "There is no page at `/{$docs->path}` in {$set->manifest->name}."];

            if ($suggestions !== []) {
                $lines[] = '';
                $lines[] = 'Similar pages:';
                $lines[] = '';

                foreach ($suggestions as $suggestion) {
                    $lines[] = "- [{$suggestion['title']}]({$suggestion['url']})".($suggestion['description'] ? ": {$suggestion['description']}" : '');
                }
            }

            $lines[] = '';
            $lines[] = 'Docs index: '.$this->urls->llms($set);

            return new Response(implode("\n", $lines)."\n", 404, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Vary' => 'Accept']);
        }

        if ($docs->isJson()) {
            return new JsonResponse(['message' => 'Page not found.', 'suggestions' => $suggestions], 404);
        }

        return new Response(View::make('larecipe::not-found', [
            'set' => $set,
            'docs' => $docs,
            'suggestions' => $suggestions,
            'home' => $this->urls->base($set),
            'assets' => $this->assets,
        ])->render(), 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
