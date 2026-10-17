<?php

namespace LaRecipe\Domains\Access\Actions;

use LaRecipe\Domains\Access\Services\ShareLinkSigner;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Support\Contracts\Clock;

/**
 * True when a share token opens this exact page right now. Any other page, an
 * expired token or a tampered one gets the same answer as no token at all.
 */
final class VerifyShareLinkAction
{
    public function __construct(
        private readonly ShareLinkSigner $signer,
        private readonly Clock $clock,
    ) {}

    public function execute(mixed $token, PageRef $ref): bool
    {
        return is_string($token) && $token !== '' && $this->signer->verify($ref, $token, $this->clock->now()->getTimestamp());
    }
}
