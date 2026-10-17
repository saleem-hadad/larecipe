<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Adds `php artisan larecipe:agents` to composer.json's post-update-cmd, so
 * the AGENTS.md block, the skills and the hook patterns follow every package
 * update. Only with the user's consent, and merged into existing scripts.
 */
final class AddComposerScriptAction
{
    public const SCRIPT = '@php artisan larecipe:agents --ansi';

    public function __construct(private readonly JsonSettings $json) {}

    public function execute(Workspace $workspace): FileChange
    {
        $composer = $this->json->decode($workspace->read('composer.json'));

        if ($composer === null || $workspace->read('composer.json') === null) {
            return $workspace->skip('composer.json', 'No readable composer.json. Add "'.self::SCRIPT.'" to post-update-cmd yourself.');
        }

        $scripts = (array) ($composer['scripts']['post-update-cmd'] ?? []);

        foreach ($scripts as $script) {
            if (is_string($script) && str_contains($script, 'larecipe:agents')) {
                return $workspace->write('composer.json', (string) $workspace->read('composer.json'));
            }
        }

        $scripts[] = self::SCRIPT;
        $composer['scripts'] = (array) ($composer['scripts'] ?? []);
        $composer['scripts']['post-update-cmd'] = array_values($scripts);

        // Composer writes four-space JSON; keep the file the way Composer would.
        return $workspace->write('composer.json', (string) json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}
