<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Contracts\LinkResolver;
use LaRecipe\Domains\Markdown\Support\AbsoluteUrls;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use Symfony\Component\Yaml\Yaml;

/**
 * The Markdown twin of a page, written for models: a stable YAML header, a
 * one-line pointer to the set's agent surfaces, then the content serialized
 * from the same AST the HTML comes from.
 *
 * Components degrade to portable Markdown, links and images become absolute,
 * snippet includes are inlined. Pass the same LinkResolver the HTML render uses,
 * so relative .md links resolve to the same pages; without one, relative links
 * are resolved against the page URL.
 */
class AgentMarkdownRenderer
{
    public function __construct(
        private readonly PageRenderer $renderer,
        private readonly MarkdownSerializer $serializer,
    ) {}

    /**
     * @param  array{url: string, base?: ?string, llms?: ?string, mcp?: ?string, skill?: ?string, version?: ?string, locale?: ?string}  $meta
     * @param  array<string, mixed>  $context  render context, as for the HTML render (abilities, snippets_root, ...)
     */
    public function render(Page $page, array $meta, ?LinkResolver $links = null, array $context = []): string
    {
        $document = $this->renderer->parse($page, $links, $context);
        $urls = new AbsoluteUrls($meta['url'], $meta['base'] ?? null, $page->ref->isIndex());
        $body = $this->serializer->serialize($document, $urls(...), $this->renderer->state());

        $first = $document->firstChild();

        if (! $first instanceof Heading || $first->getLevel() !== 1) {
            $body = '# '.$page->title."\n\n".$body;
        }

        return $this->header($page, $meta).$this->hints($meta).$body;
    }

    /** Stable keys, never the source frontmatter verbatim (it may hold anything). */
    private function header(Page $page, array $meta): string
    {
        $header = array_filter([
            'title' => $page->title,
            'description' => $page->description,
            'url' => $meta['url'],
            'version' => $meta['version'] ?? null,
            'locale' => $meta['locale'] ?? null,
            'last_updated' => $page->updatedAt?->format('Y-m-d'),
        ], fn ($value) => $value !== null && $value !== '');

        return "---\n".Yaml::dump($header, 2, 2)."---\n\n";
    }

    /** Agents rarely find llms.txt, MCP or skill.md unless something points at them. */
    private function hints(array $meta): string
    {
        $hints = array_filter([
            isset($meta['llms']) ? 'Docs index: '.$meta['llms'] : null,
            isset($meta['mcp']) ? 'MCP: '.$meta['mcp'] : null,
            isset($meta['skill']) ? 'Skill: '.$meta['skill'] : null,
        ]);

        return $hints !== [] ? '> '.implode(' · ', $hints)."\n\n" : '';
    }
}
