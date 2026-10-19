<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * Which change to look at, and which pages to check against it.
 *
 * $base null with $uncommitted false means "uncommitted changes only", so a
 * bare `larecipe:affected` always answers something useful. $version null is
 * each set's default version; "all" checks every version. $locale null is the
 * default locale; another locale also reports translations that fell behind
 * their source page.
 */
final readonly class AffectedQuery
{
    public function __construct(
        public ?string $set = null,
        public ?string $base = null,
        public string $head = 'HEAD',
        public bool $uncommitted = false,
        public ?string $version = null,
        public ?string $locale = null,
        public bool $drift = true,
    ) {}

    public function includesUncommitted(): bool
    {
        return $this->uncommitted || $this->base === null;
    }

    public function toArray(): array
    {
        return [
            'set' => $this->set,
            'base' => $this->base,
            'head' => $this->head,
            'uncommitted' => $this->includesUncommitted(),
            'version' => $this->version,
            'locale' => $this->locale,
        ];
    }
}
