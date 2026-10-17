<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\DTOs\InstallOptions;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\Enums\ReviewMode;
use LaRecipe\Support\Paths;

/**
 * Records the installer's infrastructure choices in config/larecipe.php: the
 * default set's driver and folder, and the review mode.
 *
 * With every default kept, nothing is published: the package config already
 * says the same. Otherwise the config is published (when it is not yet) and
 * only the exact default lines are changed. A config the team has reshaped is
 * never rewritten; the installer says which line to change instead.
 */
final class WriteAppConfigAction
{
    public const FILE = 'config/larecipe.php';

    private const DRIVER = "'driver' => 'repository',";

    private const PATH = "'path' => base_path('docs'),";

    private const REVIEW = '/\'review\' => env\(\'LARECIPE_REVIEW\', \'(required|auto)\'\),/';

    public function execute(Workspace $workspace, InstallOptions $options): ?FileChange
    {
        $defaults = ! $options->isDatabase() && trim($options->path, '/') === 'docs' && $options->review === ReviewMode::Required;
        $current = $workspace->read(self::FILE);

        if ($current === null && $defaults) {
            return null;
        }

        $config = $current ?? (string) file_get_contents(Paths::package('config/larecipe.php'));
        $updated = $config;
        $missing = [];

        if ($options->isDatabase()) {
            $updated = $this->replaceFirst($updated, self::DRIVER, "'driver' => 'database',", $missing, "'driver' => 'database' for the default set");
        } elseif (trim($options->path, '/') !== 'docs') {
            $path = addslashes(trim($options->path, '/'));
            $updated = $this->replaceFirst($updated, self::PATH, "'path' => base_path('{$path}'),", $missing, "'path' => base_path('{$path}') for the default set");
        }

        if (preg_match(self::REVIEW, $updated)) {
            $updated = (string) preg_replace(self::REVIEW, "'review' => env('LARECIPE_REVIEW', '{$options->review->value}'),", $updated, 1);
        } elseif ($options->review !== ReviewMode::Required) {
            $missing[] = "'review' => '{$options->review->value}'";
        }

        if ($missing !== []) {
            return $workspace->skip(self::FILE, 'Your config/larecipe.php has its own shape, so LaRecipe left it alone. Set '.implode(' and ', $missing).' yourself.');
        }

        return $workspace->write(self::FILE, $updated);
    }

    /** @param list<string> $missing */
    private function replaceFirst(string $config, string $search, string $replace, array &$missing, string $instruction): string
    {
        $position = strpos($config, $search);

        if ($position === false) {
            $missing[] = $instruction;

            return $config;
        }

        return substr_replace($config, $replace, $position, strlen($search));
    }
}
