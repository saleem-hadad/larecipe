<?php

namespace LaRecipe\Domains\Delivery\Events;

use Illuminate\Http\Request;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;

/**
 * A docs page was served (HTML, agent Markdown or navigation JSON). Hosts listen
 * to forward traffic, including agent requests client-side analytics never see,
 * to their own tools. LaRecipe stores nothing.
 */
final class PageViewed
{
    public readonly string $kind;

    public readonly ?string $userAgent;

    public function __construct(
        public readonly string $set,
        public readonly PageRef $page,
        public readonly string $format,
        Request $request,
    ) {
        $this->kind = (new RequestClassifier)->classify($request, $format);
        $this->userAgent = $request->userAgent();
    }
}
