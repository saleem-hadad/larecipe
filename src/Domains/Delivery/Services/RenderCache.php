<?php

namespace LaRecipe\Domains\Delivery\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Markdown\Services\MarkdownEnvironmentFactory;

/**
 * Content-addressed cache of rendered articles. A changed page gets a new key,
 * so nothing is ever purged by hand.
 */
final class RenderCache
{
    public const RENDERER_VERSION = '3.0.0-dev.1';

    public function __construct(
        private readonly Cache $cache,
        private readonly MarkdownEnvironmentFactory $environment,
    ) {}

    /** @param Closure(): RenderedPage $render */
    public function remember(Page $page, string $variant, Closure $render): RenderedPage
    {
        $key = 'larecipe:render:'.hash('sha256', implode('|', [
            self::RENDERER_VERSION,
            $page->ref->key(),
            $page->contentHash,
            $this->environment->version(),
            $variant,
        ]));

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return RenderedPage::fromArray($cached);
        }

        $rendered = $render();

        if ($rendered->issues === []) {
            $this->cache->forever($key, $rendered->toArray());
        }

        return $rendered;
    }
}
