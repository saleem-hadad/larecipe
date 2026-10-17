<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Workspace;

/**
 * Is Laravel Boost installed in this app?
 *
 * With Boost, LaRecipe's guideline and skills ship through Boost (it writes
 * them into every agent's files), so LaRecipe does not write its own AGENTS.md
 * block or skill copies and the instructions never appear twice.
 */
final class DetectBoostAction
{
    public function execute(Workspace $workspace): bool
    {
        if ($workspace->isDirectory('vendor/laravel/boost')) {
            return true;
        }

        $composer = json_decode((string) $workspace->read('composer.json'), true);

        if (! is_array($composer)) {
            return false;
        }

        return isset($composer['require']['laravel/boost']) || isset($composer['require-dev']['laravel/boost']);
    }
}
