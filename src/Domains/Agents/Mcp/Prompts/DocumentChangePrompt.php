<?php

namespace LaRecipe\Domains\Agents\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * document-change: bring the docs in line with a code change before it is
 * pushed, touching only what the change made wrong.
 */
#[Name('document-change')]
#[Title('Document a code change')]
#[Description('Steps for updating the docs a code change made stale, in the same change as the code.')]
final class DocumentChangePrompt extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument('change', 'The code change: a summary, a diff or a commit range.', true),
            new Argument('set', 'The doc set, on a server that serves several.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $change = trim((string) $request->get('change', 'the change you just made'));

        return Response::text(implode("\n", [
            'A code change may have made some docs pages stale. The change:',
            '',
            $change,
            '',
            '1. Find the affected pages: run php artisan larecipe:affected --uncommitted (or call affected_pages when this server offers it), then search_docs for the names the change touches: classes, routes, commands, config keys.',
            '2. Read each affected page with read_page.',
            '3. Update only what the change made wrong. Keep unrelated text as it is.',
            '4. If the change adds something no page covers, write a page for it (see the write-docs-page prompt).',
            '5. Run validate_docs and fix what it reports.',
            '6. Summarize the docs change in one line for the pull request, and push it with the code.',
        ]));
    }
}
