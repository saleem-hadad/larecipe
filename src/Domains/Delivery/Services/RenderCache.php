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
 *
 * A page that includes snippets also depends on those files, which its content
 * hash cannot see; a cached render whose included files changed is rendered
 * again. Renders with errors (an unknown component, a missing snippet) are not
 * cached, so the fix shows up on the next request; warnings (a video
 * placeholder) do not block caching.
 */
final class RenderCache
{
    public const RENDERER_VERSION = '3.0.0-dev.2';

    public function __construct(
        private readonly Cache $cache,
        private readonly MarkdownEnvironmentFactory $environment,
    ) {}

    /** @param Closure(): RenderedPage $render */
    public function remember(Page $page, string $variant, Closure $render): RenderedPage
    {
        $key = $this->key($page, $variant);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            $rendered = RenderedPage::fromArray($cached);

            if (! $rendered->dependenciesChanged()) {
                return $rendered;
            }
        }

        $rendered = $render();

        if (! $rendered->hasErrors()) {
            $this->cache->forever($key, $rendered->toArray());
        }

        return $rendered;
    }

    public function key(Page $page, string $variant): string
    {
        return 'larecipe:render:'.hash('sha256', implode('|', [
            self::RENDERER_VERSION,
            $page->ref->key(),
            $page->contentHash,
            $this->environment->version(),
            $variant,
        ]));
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
