<?php

namespace LaRecipe\Domains\Onboarding\Services;

use LaRecipe\Domains\Onboarding\DTOs\AppFacts;
use LaRecipe\Domains\Sync\Contracts\Workspace;

/**
 * Reads the facts the scaffold is built from: composer.json, the README and
 * the app's name and Laravel version. Nothing here guesses or calls an AI.
 */
final class ProjectFacts
{
    /** Packages worth naming on an Introduction page: Laravel's own, and the ecosystem's big pieces. */
    private const FIRST_PARTY = '#^(laravel|livewire|inertiajs|filament)/#';

    /** Packages every app has, which say nothing about this one. */
    private const NOISE = ['laravel/framework', 'laravel/tinker', 'laravel/pail', 'laravel/pint', 'laravel/sail', 'laravel/boost', 'laravel/prompts'];

    public function read(Workspace $workspace, string $appName, ?string $laravelVersion): AppFacts
    {
        $composer = json_decode((string) $workspace->read('composer.json'), true);
        $composer = is_array($composer) ? $composer : [];
        $require = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require-dev'] ?? []));

        $packages = array_values(array_filter(
            array_keys($require),
            fn (string $package) => preg_match(self::FIRST_PARTY, $package) && ! in_array($package, self::NOISE, true),
        ));
        sort($packages);

        $name = trim($appName);

        return new AppFacts(
            name: $name === '' || $name === 'Laravel' ? $this->nameFromComposer($composer) ?? ($name ?: 'Laravel') : $name,
            laravelVersion: $laravelVersion,
            php: isset($composer['require']['php']) ? (string) $composer['require']['php'] : null,
            packages: $packages,
            readmeIntro: $this->readmeIntro($workspace),
            description: isset($composer['description']) && trim((string) $composer['description']) !== '' ? trim((string) $composer['description']) : null,
        );
    }

    /** "acme/billing-app" becomes "Billing App", for apps still named "Laravel". */
    private function nameFromComposer(array $composer): ?string
    {
        $name = (string) ($composer['name'] ?? '');

        if ($name === '' || $name === 'laravel/laravel') {
            return null;
        }

        return ucwords(str_replace(['-', '_'], ' ', basename($name)));
    }

    /**
     * The README's opening: the paragraphs after the first heading, up to the
     * next heading. Badges, images and HTML are dropped.
     */
    private function readmeIntro(Workspace $workspace): ?string
    {
        $readme = null;

        foreach (['README.md', 'readme.md', 'Readme.md'] as $file) {
            $readme ??= $workspace->read($file);
        }

        if ($readme === null) {
            return null;
        }

        $lines = preg_split('/\R/', $readme) ?: [];
        $paragraphs = [];
        $seenHeading = false;

        foreach ($lines as $line) {
            if (preg_match('/^#{1,6}\s/', $line)) {
                if ($seenHeading && $paragraphs !== []) {
                    break;
                }

                $seenHeading = true;

                continue;
            }

            if (preg_match('/^\s*(<|!\[|\[!\[|```|---|===)/', $line)) {
                continue;
            }

            $paragraphs[] = rtrim($line);
        }

        $intro = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $paragraphs)) ?? '');

        // The Laravel skeleton's README describes Laravel, not this app.
        if ($intro === '' || str_contains($intro, 'Laravel is a web application framework with expressive, elegant syntax')) {
            return null;
        }

        if (mb_strlen($intro) <= 1200) {
            return $intro;
        }

        // Long READMEs: keep whole paragraphs up to about 1,200 characters.
        $cut = mb_strrpos(mb_substr($intro, 0, 1200), "\n\n");

        return rtrim(mb_substr($intro, 0, $cut === false ? 1200 : $cut));
    }
}
