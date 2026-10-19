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
 * write-docs-page: the steps for writing a new page well, in the order that
 * avoids the usual mistakes (duplicates, pasted values, unvalidated output).
 */
#[Name('write-docs-page')]
#[Title('Write a docs page')]
#[Description('Steps for writing a new docs page: check what exists, write from the code, add it to the navigation and validate.')]
final class WriteDocsPagePrompt extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument('topic', 'What the page should cover.', true),
            new Argument('path', 'Where the page should live, for example "billing/refunds".'),
            new Argument('set', 'The doc set, on a server that serves several.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $topic = trim((string) $request->get('topic', 'the topic you were given'));
        $path = trim((string) $request->get('path', ''));
        $set = trim((string) $request->get('set', ''));
        $where = $path !== '' ? " at \"{$path}\"" : '';
        $in = $set !== '' ? " in the \"{$set}\" doc set" : '';

        return Response::text(implode("\n", [
            "Write a docs page about {$topic}{$where}{$in}.",
            '',
            '1. Search first. Call search_docs with the main terms and read_page on anything close. If a page already covers it, update that page instead of writing a second one.',
            '2. Read the code the page describes. Write from what the code does today, not from memory.',
            '3. Start the page with frontmatter: title, a one-sentence description, and sources (globs of the code it documents, for example ["app/Billing/**"]).',
            '4. Use components where they help: <Callout type="warning"> for pitfalls, <Steps> for procedures, <Tabs> for alternatives. Never write configuration or environment values into the page.',
            '5. Add the page to the navigation (navigation.json, or folders and frontmatter order when there is none).',
            '6. Write it with write_page when this server offers it; otherwise edit the Markdown file in the docs folder.',
            '7. Run validate_docs (or php artisan larecipe:validate) and fix every error it reports.',
        ]));
    }
}
