<?php

namespace LaRecipe\Domains\Agents\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Mcp\Prompts\DocumentChangePrompt;
use LaRecipe\Domains\Agents\Mcp\Prompts\WriteDocsPagePrompt;
use LaRecipe\Domains\Agents\Mcp\Resources\DocPageResource;
use LaRecipe\Domains\Agents\Mcp\Tools\ListPagesTool;
use LaRecipe\Domains\Agents\Mcp\Tools\ReadPageTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SearchDocsTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SubmitFeedbackTool;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Validation\Mcp\ValidateDocsTool;
use LaRecipe\LaRecipe;

/**
 * Decides what the docs server exposes for this caller.
 *
 * - Read tools (search_docs, read_page, list_pages) and submit_feedback everywhere.
 * - validate_docs on the local server, and on the web only for a caller the
 *   manageLarecipe gate allows: it reads every page, private ones included.
 * - Tools other domains register with LaRecipe::mcpTool(). Author tools always
 *   on the local server; on the web only for a database set with
 *   mcp.web.author_tools on, a signed-in caller and the manageLarecipe gate.
 *   Repository sets never expose author tools on the web: their changes belong in git.
 *   Local tools (affected_pages) only on the local server.
 */
final class ServerComposition
{
    public function __construct(
        private readonly McpScope $scope,
        private readonly LaRecipe $larecipe,
        private readonly AgentDocs $docs,
        private readonly PageAccess $access,
        private readonly Config $config,
    ) {}

    /** @return list<class-string<Tool>> */
    public function tools(): array
    {
        $set = $this->boundSet();
        $tools = [SearchDocsTool::class, ReadPageTool::class, ListPagesTool::class];

        if ($this->feedbackEnabled($set)) {
            $tools[] = SubmitFeedbackTool::class;
        }

        if ($this->scope->isLocal() || ($set !== null && $this->access->canManage($set))) {
            $tools[] = ValidateDocsTool::class;
        }

        foreach ($this->larecipe->mcpTools() as $tool => $options) {
            if ($options['local'] && ! $this->scope->isLocal()) {
                continue;
            }

            if (! $options['author'] || $this->allowsAuthorTools($set)) {
                $tools[] = $tool;
            }
        }

        return array_values(array_unique($tools));
    }

    /** @return list<class-string<\Laravel\Mcp\Server\Resource>> */
    public function resources(): array
    {
        return [DocPageResource::class];
    }

    /** @return list<class-string<Prompt>> */
    public function prompts(): array
    {
        return [WriteDocsPagePrompt::class, DocumentChangePrompt::class];
    }

    public function name(): string
    {
        $set = $this->boundSet();

        return $set !== null ? $set->manifest->name.' Docs' : 'LaRecipe Docs';
    }

    /** The workflow every client is told on connect: search first, read before writing, validate after writing. */
    public function instructions(): string
    {
        $sets = $this->scope->isLocal() ? $this->docs->sets() : array_filter([$this->boundSet()]);
        $names = array_map(fn (DocSet $set) => $set->manifest->name, array_values($sets));
        $names = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).' and '.end($names) : ($names[0] ?? 'this app');

        $lines = [
            "The documentation for {$names}. Read it to answer questions, and keep it current when the code changes.",
            '',
            'How to work with these docs:',
            '1. Search first: call search_docs before you read or write anything.',
            '2. Read before you write: read_page returns a page as Markdown (pass section to read one heading). list_pages shows the navigation.',
            '3. Validate after you write: run validate_docs and fix every error it reports.',
            '',
            'Cite the page URLs you used. If the docs do not cover something, say so, and use submit_feedback to tell the owners when a page was wrong or missing.',
        ];

        if ($this->scope->isLocal() && count($sets) > 1) {
            $keys = implode(', ', array_map(fn (DocSet $set) => "{$set->key} ({$set->manifest->name})", $sets));
            $lines[] = '';
            $lines[] = "This server serves several doc sets: {$keys}. Pass set to choose one; the default is {$this->docs->defaultSet()->key}.";
        }

        return implode("\n", $lines);
    }

    private function boundSet(): ?DocSet
    {
        $key = $this->scope->boundSet();

        return $key === null ? null : $this->docs->set($key);
    }

    private function feedbackEnabled(?DocSet $set): bool
    {
        $default = (bool) $this->config->get('larecipe.feedback.enabled', true);

        return $set === null ? $default : (bool) $set->config->setting('feedback.enabled', $default);
    }

    private function allowsAuthorTools(?DocSet $set): bool
    {
        if ($this->scope->isLocal()) {
            return true;
        }

        return $this->scope->isWeb()
            && $set !== null
            && $set->config->driver === 'database'
            && (bool) $set->config->setting('mcp.web.author_tools', false)
            && $this->access->user() !== null
            && $this->access->canManage($set);
    }
}
