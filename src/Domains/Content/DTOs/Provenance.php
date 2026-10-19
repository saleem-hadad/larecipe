<?php

namespace LaRecipe\Domains\Content\DTOs;

use DateTimeImmutable;
use LaRecipe\Domains\Content\Enums\Actor;

/**
 * Who or what last changed something, and where the change came from.
 *
 * Provenance is metadata from the source (git history, the revisions table, a
 * docs migration), never something authors maintain by hand in frontmatter.
 */
final readonly class Provenance
{
    public function __construct(
        public Actor $actor,
        public ?string $name = null,
        public ?string $ref = null,
        public ?DateTimeImmutable $at = null,
        public ?string $approvedBy = null,
    ) {}

    public static function human(?string $name = null, ?string $ref = null, ?DateTimeImmutable $at = null): self
    {
        return new self(Actor::Human, $name, $ref, $at);
    }

    public static function agent(string $name, ?string $ref = null, ?DateTimeImmutable $at = null): self
    {
        return new self(Actor::Agent, $name, $ref, $at);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            actor: Actor::tryFrom((string) ($data['actor'] ?? 'human')) ?? Actor::Human,
            name: $data['name'] ?? null,
            ref: $data['ref'] ?? null,
            at: isset($data['at']) ? new DateTimeImmutable((string) $data['at']) : null,
            approvedBy: $data['approved_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'actor' => $this->actor->value,
            'name' => $this->name,
            'ref' => $this->ref,
            'at' => $this->at?->format(DATE_ATOM),
            'approved_by' => $this->approvedBy,
        ];
    }
}
