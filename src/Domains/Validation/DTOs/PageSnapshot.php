<?php

namespace LaRecipe\Domains\Validation\DTOs;

use LaRecipe\Domains\Access\DTOs\GateIssue;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;

/**
 * One page as validation sees it: the page, where it lives, and what rendering
 * it taught us (links, component problems, anchors, agent Markdown size).
 */
final readonly class PageSnapshot
{
    /**
     * @param  ?string  $file  the page's file relative to the set root, for messages
     * @param  bool  $folderIndex  an index.md inside a folder, so relative links start in that folder
     * @param  list<string>  $anchors  every id on the rendered page
     * @param  int  $lineOffset  lines before the body in the file (the frontmatter block), so line numbers match the file
     * @param  list<GateIssue>  $gateIssues  what the Access domain found wrong with the page's <Can> and <Cannot> abilities
     */
    public function __construct(
        public Page $page,
        public ?string $file = null,
        public bool $folderIndex = false,
        public ?RenderedPage $rendered = null,
        public array $anchors = [],
        public ?int $markdownSize = null,
        public ?string $yamlError = null,
        public ?string $renderError = null,
        public int $lineOffset = 0,
        public array $gateIssues = [],
    ) {}

    /** The file line of the first body line containing $needle, for pointing at a problem. */
    public function lineOf(string $needle): ?int
    {
        foreach (explode("\n", $this->page->body) as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index + 1 + $this->lineOffset;
            }
        }

        return null;
    }

    /** A body line number (1-based, as commonmark reports it) as a file line number. */
    public function fileLine(?int $bodyLine): ?int
    {
        return $bodyLine === null ? null : $bodyLine + $this->lineOffset;
    }
}
