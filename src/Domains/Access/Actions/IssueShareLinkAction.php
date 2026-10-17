<?php

namespace LaRecipe\Domains\Access\Actions;

use DateInterval;
use InvalidArgumentException;
use LaRecipe\Domains\Access\DTOs\ShareLink;
use LaRecipe\Domains\Access\Services\ShareLinkSigner;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Support\Contracts\Clock;

/**
 * Issues a share link: anyone holding it may read one page, private or not, in
 * every representation, until it expires. Used by `larecipe:share`.
 */
final class IssueShareLinkAction
{
    public function __construct(
        private readonly ShareLinkSigner $signer,
        private readonly Clock $clock,
    ) {}

    /** @param string $expires a lifetime such as "30m", "12h", "7d" or "2w" */
    public function execute(PageRef $ref, string $expires = '7d'): ShareLink
    {
        $expiresAt = $this->clock->now()->add($this->interval($expires));

        return new ShareLink($ref, $this->signer->sign($ref, $expiresAt->getTimestamp()), $expiresAt);
    }

    private function interval(string $expires): DateInterval
    {
        if (! preg_match('/^\s*(\d+)\s*([mhdw])\s*$/i', $expires, $matches) || (int) $matches[1] < 1) {
            throw new InvalidArgumentException("Use a lifetime like 30m, 12h, 7d or 2w, not \"{$expires}\".");
        }

        $amount = (int) $matches[1];

        return match (strtolower($matches[2])) {
            'm' => new DateInterval("PT{$amount}M"),
            'h' => new DateInterval("PT{$amount}H"),
            'w' => new DateInterval('P'.($amount * 7).'D'),
            default => new DateInterval("P{$amount}D"),
        };
    }
}
