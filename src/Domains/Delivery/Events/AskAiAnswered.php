<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * Ask AI finished an answer. covered is false when the assistant said the docs
 * do not cover the question: the signal hosts use to find missing pages.
 */
final class AskAiAnswered
{
    /**
     * @param  list<string>  $citations  page URLs the answer cited, each checked against known pages
     */
    public function __construct(
        public readonly string $set,
        public readonly string $version,
        public readonly string $locale,
        public readonly string $question,
        public readonly bool $covered,
        public readonly array $citations,
        public readonly string $kind,
        public readonly ?PageRef $page = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}
}
