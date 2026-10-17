<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Enums\VersionStatus;
use LaRecipe\Domains\Content\Enums\Visibility;

/**
 * One version of a doc set. A set without declared versions has one implicit
 * version, which never appears in URLs.
 */
final readonly class Version
{
    public const IMPLICIT = 'current';

    public function __construct(
        public string $id,
        public string $label,
        public VersionStatus $status = VersionStatus::Current,
        public bool $default = false,
        public Visibility $visibility = Visibility::Public,
        public bool $implicit = false,
    ) {}

    public static function implicit(): self
    {
        return new self(self::IMPLICIT, 'Current', VersionStatus::Current, true, Visibility::Public, true);
    }

    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');

        return new self(
            id: $id,
            label: (string) ($data['label'] ?? $id),
            status: VersionStatus::tryFrom((string) ($data['status'] ?? 'current')) ?? VersionStatus::Current,
            default: (bool) ($data['default'] ?? false),
            visibility: Visibility::tryFrom((string) ($data['visibility'] ?? 'public')) ?? Visibility::Public,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status->value,
            'default' => $this->default,
            'visibility' => $this->visibility->value,
            'implicit' => $this->implicit,
        ];
    }
}
