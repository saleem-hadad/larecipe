<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * What a source move (or an export or import) carries: the canonical files of
 * the set, counted, plus what the person running it should know first.
 */
final readonly class MovePlan
{
    /**
     * @param  list<string>  $pages  "version/locale/path" of every page
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $set,
        public bool $manifest,
        public array $pages,
        public int $navigations,
        public int $assets,
        public array $warnings = [],
    ) {}

    public function withWarnings(array $warnings): self
    {
        return new self($this->set, $this->manifest, $this->pages, $this->navigations, $this->assets, [...$this->warnings, ...$warnings]);
    }
}
