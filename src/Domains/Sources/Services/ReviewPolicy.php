<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\ReviewMode;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * Decides whether a change goes live or waits for a person (ARCHITECTURE.md 14.6).
 *
 * A change that rides in a pull request (the owner's session, a docs migration)
 * was reviewed there, so it always publishes. A person in the admin UI
 * publishes, unless they chose to save a draft. A change made outside a pull
 * request by an agent (remote MCP author tools, larecipe:sync on production)
 * follows the set's review mode: a draft under `required`, live under `auto`.
 */
final class ReviewPolicy
{
    /** @param array<string, SetConfig> $sets */
    public function __construct(private readonly array $sets) {}

    public function modeFor(string $set, Channel $channel, ?WriteMode $requested = null): WriteMode
    {
        if ($requested === WriteMode::Draft) {
            return WriteMode::Draft;
        }

        return match ($channel) {
            Channel::PullRequest, Channel::Migration, Channel::AdminUi => WriteMode::Publish,
            Channel::RemoteAgent, Channel::Sync => $this->reviewFor($set) === ReviewMode::Auto ? WriteMode::Publish : WriteMode::Draft,
        };
    }

    public function reviewFor(string $set): ReviewMode
    {
        return ReviewMode::fromConfig(isset($this->sets[$set]) ? $this->sets[$set]->review : null);
    }
}
