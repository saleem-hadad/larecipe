<?php

namespace LaRecipe\Tests\Unit\Markdown;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderAgentMarkdownAction;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;

/**
 * Renders a Markdown string the way a real page is rendered, for the
 * renderer snapshot tests.
 */
final class Render
{
    public const URL = 'https://acme.test/docs/guides/example';

    public static function page(string $body, string $path = 'guides/example', array $frontmatter = []): Page
    {
        return Page::make(new PageRef('docs', 'current', 'en', $path), new Frontmatter(['title' => 'Example'] + $frontmatter), $body);
    }

    public static function html(string $body, array $context = [], ?LinkResolver $links = null): RenderedPage
    {
        return app(RenderPageAction::class)->execute(self::page($body), $links, $context);
    }

    /** The full agent Markdown document, header included. */
    public static function document(string $body, array $meta = [], array $context = [], ?LinkResolver $links = null): string
    {
        return app(RenderAgentMarkdownAction::class)->execute(self::page($body), ['url' => self::URL] + $meta, $links, $context);
    }

    /** Only the serialized body, after the header and the "# Example" heading. */
    public static function markdown(string $body, array $context = [], ?LinkResolver $links = null): string
    {
        $document = self::document($body, [], $context, $links);

        return trim(substr($document, strpos($document, "# Example\n") + strlen("# Example\n")));
    }

    /** A link resolver that maps "x.md" to "/docs/x" and assets to "/docs/_assets/current/x", like Delivery's. */
    public static function links(): LinkResolver
    {
        return new class implements LinkResolver
        {
            public function page(PageRef $from, string $target): ?string
            {
                if (! preg_match('/^([^?#]*?)\.md(#.*)?$/i', $target, $matches)) {
                    return null;
                }

                return '/docs/'.ltrim(str_replace('../', '', $matches[1]), './').($matches[2] ?? '');
            }

            public function asset(PageRef $from, string $target): ?string
            {
                return '/docs/_assets/current/'.ltrim(str_replace('../', '', $target), './');
            }
        };
    }
}
