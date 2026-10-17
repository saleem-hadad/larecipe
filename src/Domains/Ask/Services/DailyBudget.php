<?php

namespace LaRecipe\Domains\Ask\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Support\Contracts\Clock;

/**
 * The per-day cap on Ask AI answers for a set (ai.ask.daily_budget). Answers
 * run on the owner's provider key, so the owner decides how many a day a set
 * may spend; past it, readers are pointed back to search until tomorrow.
 */
final class DailyBudget
{
    public function __construct(
        private readonly Cache $cache,
        private readonly Clock $clock,
    ) {}

    /** Count one answer against today's budget; false when the budget is spent. */
    public function take(DocSet $set): bool
    {
        $limit = $set->config->setting('ai.ask.daily_budget');

        if ($limit === null || $limit === '' || (int) $limit <= 0) {
            return true;
        }

        $key = 'larecipe:ask:'.$set->key.':'.$this->clock->now()->format('Y-m-d');

        $this->cache->add($key, 0, 60 * 60 * 25);

        return (int) $this->cache->increment($key) <= (int) $limit;
    }
}
