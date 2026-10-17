<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Content\DTOs\Page;
use Symfony\Component\Yaml\Yaml;

/**
 * The Markdown twin of a page, written for models: a stable YAML header, a
 * one-line pointer to the set's agent surfaces, then the content.
 *
 * Foundation version: the body is passed through. The Markdown track replaces
 * this with a serializer over the AST that degrades components to portable
 * Markdown, resolves live references and drops gated blocks.
 */
class AgentMarkdownRenderer
{
    /**
     * @param  array{url: string, llms?: ?string, mcp?: ?string, skill?: ?string, version?: ?string, locale?: ?string}  $meta
     */
    public function render(Page $page, array $meta): string
    {
        $header = array_filter([
            'title' => $page->title,
            'description' => $page->description,
            'url' => $meta['url'],
            'version' => $meta['version'] ?? null,
            'locale' => $meta['locale'] ?? null,
            'last_updated' => $page->updatedAt?->format('Y-m-d'),
        ], fn ($value) => $value !== null && $value !== '');

        $hints = array_filter([
            isset($meta['llms']) ? 'Docs index: '.$meta['llms'] : null,
            isset($meta['mcp']) ? 'MCP: '.$meta['mcp'] : null,
            isset($meta['skill']) ? 'Skill: '.$meta['skill'] : null,
        ]);

        $body = trim($page->body);

        if (! preg_match('/^#\s/m', $body)) {
            $body = '# '.$page->title."\n\n".$body;
        }

        return "---\n".Yaml::dump($header, 2, 2)."---\n\n"
            .($hints !== [] ? '> '.implode(' · ', $hints)."\n\n" : '')
            .$body."\n";
    }
}
