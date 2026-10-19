<?php

namespace LaRecipe\Domains\Delivery\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;

/**
 * Whether a page changed recently enough to carry the fresh dot: in the
 * sidebar, in its page header and on the admin's page list. The window is
 * larecipe.ui.fresh_days (1 by default; 0 turns the dots off).
 */
final readonly class Freshness
{
    public function __construct(private Repository $config) {}

    public function isFresh(?DateTimeInterface $updatedAt): bool
    {
        $days = (float) $this->config->get('larecipe.ui.fresh_days', 1);

        return $updatedAt !== null
            && $days > 0
            && $updatedAt > new DateTimeImmutable('-'.(int) round($days * 86400).' seconds');
    }
}
