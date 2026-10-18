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
 * when gate blocks granted the reader something). Two kinds of dependencies the
 * content hash cannot see are handled here:
 *
 * - Snippet includes: a cached render whose included files changed is rendered again.
 * - Live references: the base key holds a marker naming the components, and the
 *   render lives under the base key plus the fingerprint of the app surface it
 *   reads, so a renamed route re-renders the page.
 *
 * Renders with errors (an unknown component, a missing snippet, a broken
 * reference) are not cached, so the fix shows up on the next request; warnings
 * (a video placeholder) do not block caching.
 */
final class RenderCache
{
    public const RENDERER_VERSION = '3.0.0-dev.3';

    public function __construct(
        private readonly Cache $cache,
        private readonly MarkdownEnvironmentFactory $environment,
        private readonly ReferencesFingerprintAction $references,
    ) {}

    /** @param Closure(): RenderedPage $render */
    public function remember(Page $page, string $variant, Closure $render): RenderedPage
    {
        $key = $this->key($page, $variant);
        $cached = $this->cache->get($key);

        if (is_array($cached) && isset($cached['references'])) {
            $cached = $this->cache->get($this->referencesKey($key, (array) $cached['references']));
        }

        if (is_array($cached) && isset($cached['html'])) {
            $rendered = RenderedPage::fromArray($cached);

            if (! $rendered->dependenciesChanged()) {
                return $rendered;
            }
        }

        $rendered = $render();

        if ($rendered->hasErrors()) {
            return $rendered;
        }

        if ($rendered->hasLiveReferences) {
            $this->cache->forever($key, ['references' => $rendered->components]);
            $this->cache->forever($this->referencesKey($key, $rendered->components), $rendered->toArray());
        } else {
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
            $this->environment->version($page->ref->set),
            $variant,
        ]));
    }

    public function forget(string $key): void
    {
        $cached = $this->cache->get($key);

        if (is_array($cached) && isset($cached['references'])) {
            $this->cache->forget($this->referencesKey($key, (array) $cached['references']));
        }

        $this->cache->forget($key);
    }

    /** @param list<string> $components */
    private function referencesKey(string $key, array $components): string
    {
        return $key.':'.$this->references->execute($components);
    }
}
