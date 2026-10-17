<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Illuminate\Container\Container;
use LaRecipe\Domains\Sync\Actions\BuildInstructionsAction;
use Throwable;

/**
 * Renders LaRecipe's instructions inside the Laravel Boost guideline
 * (resources/boost/guidelines/core.blade.php).
 *
 * Boost renders package guidelines with Blade inside the booted app, so the
 * guideline calls this to get exactly the text the AGENTS.md block would hold.
 * A Blade template cannot receive constructor injection, which is why this
 * one adapter asks the container. If anything goes wrong, a static version is
 * returned: Boost skips a guideline whose render fails, and missing
 * instructions are worse than generic ones.
 */
final class BoostGuideline
{
    public static function instructions(): string
    {
        try {
            return Container::getInstance()->make(BuildInstructionsAction::class)->execute();
        } catch (Throwable) {
            return self::fallback();
        }
    }

    private static function fallback(): string
    {
        return implode("\n", [
            '## Documentation (LaRecipe)',
            '',
            "This app's docs are served by LaRecipe. Docs ship in the same push as the code they describe.",
            '',
            'Before you push a branch or open a pull request:',
            '',
            '1. Run `php artisan larecipe:affected --uncommitted --base=main` to list the docs pages your change affects.',
            '2. Update each affected page with the `larecipe-update-docs` skill. Keep unrelated text untouched.',
            '3. Run `php artisan larecipe:validate` and fix what it reports.',
            '4. Commit the docs with the code and push them together.',
            '',
            'Review mode is `required`: show the user the docs diff and wait for approval before committing it.',
            '',
            'Search and read the docs with the `larecipe` MCP server. Never write configuration or environment values into docs.',
        ]);
    }
}
