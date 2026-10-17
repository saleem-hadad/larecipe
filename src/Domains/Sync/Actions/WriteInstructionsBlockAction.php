<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Services\MarkedBlock;

/**
 * Writes LaRecipe's section into AGENTS.md at the project root, between
 * `<!-- larecipe:start -->` and `<!-- larecipe:end -->`.
 *
 * AGENTS.md belongs to the team, so only the marked block is ever replaced:
 * text above and below it survives every regeneration byte for byte. The
 * file is created when it does not exist.
 */
final class WriteInstructionsBlockAction
{
    public function __construct(
        private readonly BuildInstructionsAction $build,
        private readonly SyncSettings $settings,
    ) {}

    public function execute(Workspace $workspace, ?bool $withIndex = null, ?string $indexSet = null): FileChange
    {
        $file = $this->settings->instructionsFile;
        $body = $this->build->execute($withIndex, $indexSet);

        return $workspace->write($file, (new MarkedBlock)->write($workspace->read($file), $body));
    }
}
