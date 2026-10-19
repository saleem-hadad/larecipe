<?php

namespace LaRecipe\Domains\Access\Actions;

use LaRecipe\Domains\Access\Blocks\GateBlockLog;
use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;

/**
 * Gives a page's raw Markdown the reader's view of its gate blocks: blocks the
 * reader may not see are cut out, and the tags of blocks they keep are dropped.
 *
 * For surfaces that start from the page source instead of the AST (the source
 * agent Markdown renderer, llms-full.txt, exports). The decision is not made
 * here: the page is parsed with the same GateBlockFilter every renderer uses,
 * and only the line ranges it reports are projected back onto the source, so
 * the source view and the AST view can never disagree.
 */
final class FilterGatedSourceAction
{
    private const TAG_LINE = '#^[\s>]*</?(?:Can|Cannot)(?:\s[^>]*)?/?>\s*$#';

    public function __construct(private readonly RenderPageAction $render) {}

    public function execute(Page $page, Audience $audience): Page
    {
        if ($audience->unrestricted) {
            return $page;
        }

        $log = new GateBlockLog;
        $this->render->execute($page, null, $audience->toContext() + [GateBlockLog::CONTEXT_KEY => $log]);

        if ($log->isEmpty()) {
            return $page;
        }

        $lines = explode("\n", $page->body);
        $replace = [];

        foreach ($log->removed as [$start, $end]) {
            $replace[$start] ??= $this->prefix($lines[$start - 1] ?? '');

            for ($line = $start + 1; $line <= $end; $line++) {
                $replace[$line] = null;
            }
        }

        foreach ($log->tags as $line) {
            // Only a real tag line is dropped: an unclosed block ends on content, not a tag.
            if (! array_key_exists($line, $replace) && preg_match(self::TAG_LINE, $lines[$line - 1] ?? '') === 1) {
                $replace[$line] = $this->prefix($lines[$line - 1]);
            }
        }

        $kept = [];

        foreach ($lines as $index => $text) {
            $number = $index + 1;

            if (! array_key_exists($number, $replace)) {
                $kept[] = $text;
            } elseif ($replace[$number] !== null) {
                // A blank line (inside the same quote or list) keeps neighbouring paragraphs apart.
                $kept[] = $replace[$number];
            }
        }

        $body = preg_replace("/\n{3,}/", "\n\n", implode("\n", $this->withoutEmptyQuotes($kept))) ?? '';

        return new Page(
            ref: $page->ref,
            title: $page->title,
            description: $page->description,
            frontmatter: $page->frontmatter,
            body: $body,
            contentHash: Page::hash($page->frontmatter, $body),
            updatedAt: $page->updatedAt,
            provenance: $page->provenance,
        );
    }

    /**
     * Drops a quote marker left on its own when the block it quoted was removed,
     * as the AST filter drops the emptied quote.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function withoutEmptyQuotes(array $lines): array
    {
        $isQuote = fn (?string $line) => $line !== null && str_starts_with(ltrim($line), '>');
        $kept = [];

        foreach ($lines as $index => $line) {
            if (trim($line, " \t>") === '' && trim($line) !== '' && ! $isQuote($lines[$index - 1] ?? null) && ! $isQuote($lines[$index + 1] ?? null)) {
                continue;
            }

            $kept[] = $line;
        }

        return $kept;
    }

    /** The container markers in front of a line ("> " in a quote), so a blank line stays inside it. */
    private function prefix(string $line): string
    {
        preg_match('/^[\s>]*/', $line, $matches);

        return rtrim($matches[0] ?? '');
    }
}
