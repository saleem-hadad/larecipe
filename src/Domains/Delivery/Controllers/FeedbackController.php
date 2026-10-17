<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Events\FeedbackSubmitted;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;

/**
 * POST /{prefix}/_feedback: "Was this page helpful?" from the page widget (and
 * Ask AI's thumbs).
 *
 * It dispatches FeedbackSubmitted for the host to forward to its own tools and
 * stores nothing. The route runs in the set's middleware group, so the web
 * group's CSRF protection and session apply. Feedback on a page the caller may
 * not see is a 404, like any other request for it.
 */
final class FeedbackController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly RequestClassifier $classifier,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->current->set($request);

        if (! $set->config->setting('feedback.enabled', (bool) config('larecipe.feedback.enabled', true)) || ! $this->access->canEnter($set)) {
            abort(404);
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:300'],
            'version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
            'helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $version = $set->isVersioned() && ! empty($data['version']) ? $data['version'] : $set->defaultVersion()->id;
        $locale = ! empty($data['locale']) ? $data['locale'] : $set->defaultLocale()->code;

        if ($set->version($version) === null || $set->locale($locale) === null || ! $this->access->canViewVersion($set, $version)) {
            abort(404);
        }

        $path = PageRef::normalizePath((string) $data['path']);
        $summary = $this->catalog->summaries($set, $version, $locale)[$path] ?? null;

        if ($summary === null || ! $this->access->canView($set, $summary)) {
            abort(404);
        }

        $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';

        event(new FeedbackSubmitted(
            $set->key,
            $summary->ref,
            filter_var($data['helpful'], FILTER_VALIDATE_BOOLEAN),
            $comment === '' ? null : $comment,
            'reader',
            $this->classifier->classify($request),
        ));

        return new Response('', 204);
    }
}
