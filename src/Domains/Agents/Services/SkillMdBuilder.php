<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Content\DTOs\DocSet;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes a set's skill.md: how an agent should use the documented product's
 * docs, in the portable Agent Skills format (frontmatter with name and
 * description only, so every agent can load it).
 *
 * It is generated from the manifest (name, description, agent instructions),
 * the navigation (key pages with their Markdown URLs) and the set's connection
 * details (MCP, llms.txt). A hand-written skill named like the set replaces it.
 */
final class SkillMdBuilder
{
    private const KEY_PAGES = 40;

    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly OutlineBuilder $outlines,
        private readonly SetDescriber $describe,
        private readonly LlmsTxtWriter $writer,
        private readonly SkillLibrary $library,
    ) {}

    /** The generated skill's name: the set's name as a valid skill name ("Acme Billing" becomes "acme-billing"). */
    public function name(DocSet $set): string
    {
        $name = trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $set->manifest->name)), '-');

        if ($name === '') {
            $name = trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $set->key)), '-') ?: 'docs';
        }

        return rtrim(substr($name, 0, 64), '-');
    }

    /** The hand-written skill that replaces the generated one: named like the set's skill or like the set key. */
    public function override(DocSet $set): ?string
    {
        $skills = $this->library->all($set);

        return $skills[$this->name($set)] ?? $skills[$set->key] ?? null;
    }

    public function build(DocSet $set): string
    {
        $override = $this->override($set);

        if ($override !== null) {
            return (string) file_get_contents($override);
        }

        $version = $set->defaultVersion()->id;
        $locale = $set->defaultLocale()->code;
        $name = $set->manifest->name;

        $frontmatter = Yaml::dump(['name' => $this->name($set), 'description' => $this->description($set)], 2, 2);
        $lines = ['---', rtrim($frontmatter), '---', '', '# '.$name, '', $this->describe->summary($set), ''];

        if (($instructions = $this->describe->instructions($set)) !== null) {
            array_push($lines, $instructions, '');
        }

        array_push($lines, ...$this->reading($set), ...$this->connect($set, $version), ...$this->keyPages($set, $version, $locale));

        return rtrim(implode("\n", $lines))."\n";
    }

    public function description(DocSet $set): string
    {
        $name = $set->manifest->name;
        $about = $set->manifest->description;
        $description = "Use when working with {$name}".($about !== null && trim($about) !== '' ? ': '.rtrim(trim($about), '.') : '')
            .". Explains how to search and read the {$name} docs (MCP tools, llms.txt and a Markdown version of every page) and lists the key pages.";

        return mb_strlen($description) <= 1024 ? $description : rtrim(mb_substr($description, 0, 1021)).'...';
    }

    /** @return list<string> */
    private function reading(DocSet $set): array
    {
        $mcp = $this->links->mcp($set);
        $llms = $this->links->llms($set);

        return [
            '## Read the docs',
            '',
            $mcp !== null
                ? "1. Search first: call `search_docs` on the MCP server at {$mcp}, or scan the index at {$llms}."
                : "1. Search first: scan the index at {$llms}.",
            '2. Read whole pages as Markdown: '.($mcp !== null ? 'call `read_page`, or ' : '').'add `.md` to any page URL.',
            '3. Answer from the pages you read, and cite their URLs.',
            '',
        ];
    }

    /** @return list<string> */
    private function connect(DocSet $set, string $version): array
    {
        $lines = ['## Connect', ''];

        if (($mcp = $this->links->mcp($set)) !== null) {
            $lines[] = "- MCP server (streamable HTTP): {$mcp}";
        }

        $lines[] = '- Docs index: '.$this->links->llms($set);

        if ($set->config->setting('agents.llms_full', true)) {
            $lines[] = '- Full text: '.$this->links->llmsFull($set, $version);
        }

        if ($set->isVersioned()) {
            $versions = array_map(
                fn ($v) => $v->label.($v->default ? ' (default)' : ''),
                array_values(array_filter($set->versions(), fn ($v) => $this->docs->canViewVersion($set, $v->id))),
            );
            $lines[] = '- Versions: '.implode(', ', $versions);
        }

        $lines[] = '';

        return $lines;
    }

    /** @return list<string> */
    private function keyPages(DocSet $set, string $version, string $locale): array
    {
        $outline = $this->outlines->build($set, $version, $locale);
        $lines = ['## Key pages', ''];
        $count = 0;

        foreach ($outline->sections as $section) {
            $entries = array_slice($section->allEntries(), 0, max(0, self::KEY_PAGES - $count));

            if ($entries === []) {
                break;
            }

            array_push($lines, '### '.$section->heading, '');

            foreach ($entries as $entry) {
                $lines[] = $this->writer->entry($entry);
            }

            $lines[] = '';
            $count += count($entries);
        }

        return $count === 0 ? [] : $lines;
    }
}
