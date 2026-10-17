<?php

namespace LaRecipe\Domains\Delivery\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Markdown\Services\MarkdownEnvironmentFactory;
use LaRecipe\Domains\References\Actions\ReferencesFingerprintAction;

/**
 * Content-addressed cache of rendered articles. A changed page gets a new key,
 * so nothing is ever purged by hand.
 *
 * The variant names who the render is for (the set's mount, plus the audience
 * when gate blocks granted the reader something). A page with live references
 * is also keyed by the fingerprint of the app surface it reads: its base key
 * holds a marker naming the components, and the render lives under the base
 * key plus the current fingerprint, so a renamed route re-renders it.
 */
final class RenderCache
{
    public const RENDERER_VERSION = '3.0.0-dev.1';

    public function __construct(
        private readonly Cache $cache,
        private readonly MarkdownEnvironmentFactory $environment,
        private readonly ReferencesFingerprintAction $references,
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

        if (is_array($cached) && isset($cached['references'])) {
            $cached = $this->cache->get($key.':'.$this->references->execute((array) $cached['references']));
        }

        if (is_array($cached) && isset($cached['html'])) {
            return RenderedPage::fromArray($cached);
        }

        $rendered = $render();

        if ($rendered->issues !== []) {
            return $rendered;
        }

        if ($rendered->hasLiveReferences) {
            $this->cache->forever($key, ['references' => $rendered->components]);
            $this->cache->forever($key.':'.$this->references->execute($rendered->components), $rendered->toArray());
        } else {
            $this->cache->forever($key, $rendered->toArray());
        }

        return $rendered;
    }
}
