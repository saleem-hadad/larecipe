<?php

namespace LaRecipe\Domains\Markdown\Parser;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A `<<< @/app/Billing/Invoice.php#refund` line in the AST, before it is
 * resolved. The document transformer replaces it with a code block holding the
 * real code, or with a visible notice when the file or region is missing.
 */
final class SnippetInclude extends AbstractBlock
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $region = null,
        public readonly string $meta = '',
    ) {
        parent::__construct();
    }

    /** What the author wrote, for notices and validation messages. */
    public function target(): string
    {
        return $this->path.($this->region !== null ? '#'.$this->region : '');
    }
}
