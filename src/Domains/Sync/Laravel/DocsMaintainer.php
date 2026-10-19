<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

/**
 * The server-side docs agent behind `php artisan larecipe:sync`
 * (ARCHITECTURE.md 13.6), on the owner's own provider key.
 *
 * It runs inside the booted app, so it writes from what the app actually
 * does: the same MCP tool classes every coding agent uses (search_docs,
 * read_page, list_pages, affected_pages, validate_docs and the author
 * tools, which go through WritePageAction and the review mode), plus
 * read-only code, diff and live reference tools that never reach
 * configuration or environment values. Its instructions are the
 * larecipe-update-docs and larecipe-audit-docs skills.
 *
 * Everything that varies per run arrives in the constructor, so the class
 * stays a plain, fakeable laravel/ai agent (DocsMaintainer::fake()).
 */
final class DocsMaintainer implements Agent, HasTools
{
    use Promptable;

    /** @param list<object> $agentTools laravel/ai tools, already guarded for this run */
    public function __construct(
        private readonly string $system,
        private readonly array $agentTools,
        private readonly int $steps = 30,
        private readonly ?string $providerName = null,
        private readonly ?string $modelName = null,
        private readonly int $seconds = 120,
    ) {}

    public function instructions(): string
    {
        return $this->system;
    }

    public function tools(): iterable
    {
        return $this->agentTools;
    }

    public function maxSteps(): int
    {
        return $this->steps;
    }

    public function provider(): ?string
    {
        return $this->providerName;
    }

    public function model(): ?string
    {
        return $this->modelName;
    }

    public function timeout(): int
    {
        return $this->seconds;
    }
}
