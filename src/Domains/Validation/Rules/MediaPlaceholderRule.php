<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\MarkdownScanner;

/**
 * media.placeholder: a <Video placeholder="..."> without a src yet. It renders
 * as a labelled frame, which is right while a recording is pending; this
 * warning makes sure it is never forgotten.
 */
final class MediaPlaceholderRule implements PageRule
{
    public function __construct(private readonly MarkdownScanner $scanner) {}

    public function check(PageSnapshot $page, PageContext $context): array
    {
        $issues = [];

        foreach ($this->scanner->tags($page->page->body, 'Video') as $tag) {
            if (array_key_exists('placeholder', $tag['props']) && empty($tag['props']['src'])) {
                $issues[] = ValidationIssue::warning(
                    'media.placeholder',
                    'A video placeholder is waiting for its recording: "'.$tag['props']['placeholder'].'".',
                    $context->pageId($page->page->ref->path),
                    $page->fileLine($tag['line']),
                    'Add src="..." when the video exists.',
                    $page->file,
                );
            }
        }

        return $issues;
    }
}
