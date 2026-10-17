<?php

namespace LaRecipe\Domains\Onboarding\DTOs;

/**
 * What the installer can read about the app without guessing: its name,
 * its Laravel version, the first-party packages it requires and the opening
 * of its README. The scaffolded Introduction page is built from these facts
 * only, so it is short but never wrong.
 */
final readonly class AppFacts
{
    /** @param list<string> $packages first-party packages from composer.json */
    public function __construct(
        public string $name,
        public ?string $laravelVersion = null,
        public ?string $php = null,
        public array $packages = [],
        public ?string $readmeIntro = null,
        public ?string $description = null,
    ) {}

    /** The minor PHP version CI should run, from composer.json's constraint ("^8.3" gives "8.3"). */
    public function phpVersion(): string
    {
        if ($this->php !== null && preg_match('/(\d+\.\d+)/', $this->php, $match)) {
            return version_compare($match[1], '8.3', '<') ? '8.3' : $match[1];
        }

        return '8.4';
    }
}
