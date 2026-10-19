<?php

namespace LaRecipe\Domains\Search\DTOs;

/**
 * The search index one audience downloads: MiniSearch options plus the records
 * it may see, as JSON. The browser indexes the records on first open.
 */
final readonly class ClientIndex
{
    public const BUDGET_KB = 300;

    /**
     * @param  list<array{rule: string, level: string, message: string}>  $issues
     */
    public function __construct(
        public Audience $audience,
        public string $json,
        public int $records,
        public int $bytes,
        public int $gzipBytes,
        public array $issues = [],
    ) {}

    public function overBudget(int $budgetKb = self::BUDGET_KB): bool
    {
        return $this->gzipBytes > $budgetKb * 1024;
    }
}
