<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;

/**
 * Compiles the hook settings into bootstrap/cache/larecipe/hooks.php.
 *
 * vendor/bin/larecipe-hook runs on every Bash call in an agent session and
 * must not boot Laravel to learn which commands push. It includes this plain
 * PHP array instead. Without it, the hook falls back to the default patterns.
 */
final class CompileHookPatternsAction
{
    public function __construct(private readonly SyncSettings $settings) {}

    public function execute(Workspace $workspace): FileChange
    {
        $data = [
            'enabled' => $this->settings->hooksEnabled,
            'push_commands' => $this->settings->pushCommands,
        ];

        $contents = "<?php\n\n"
            ."// Compiled by php artisan larecipe:agents for vendor/bin/larecipe-hook, so ordinary\n"
            ."// shell commands never boot the framework. Change config/larecipe.php instead.\n\n"
            .'return '.var_export($data, true).";\n";

        return $workspace->write(rtrim($this->settings->cacheDirectory, '/').'/hooks.php', $contents);
    }
}
