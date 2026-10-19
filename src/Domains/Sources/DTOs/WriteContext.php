<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Sources\Enums\Channel;

/**
 * Why a change is being written, beyond who writes it: where it came from, the
 * one-line summary for the revision list and the PR, the content hash it was
 * written against, and the docs migration that carries it.
 *
 * Sources record it on the revision. Repository sources ignore it; git keeps
 * that history.
 */
final readonly class WriteContext
{
    public function __construct(
        public Channel $channel = Channel::PullRequest,
        public ?string $summary = null,
        public ?string $basedOn = null,
        public ?string $migration = null,
    ) {}

    public static function migration(string $name, ?string $summary = null): self
    {
        return new self(Channel::Migration, $summary, null, $name);
    }

    public function withBasedOn(?string $hash): self
    {
        return new self($this->channel, $this->summary, $hash, $this->migration);
    }
}
