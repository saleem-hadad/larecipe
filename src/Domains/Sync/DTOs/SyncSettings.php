<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Support\Arr;

/**
 * The Sync domain's configuration, read once from config/larecipe.php by the
 * service provider, so portable Actions never touch config() themselves.
 */
final readonly class SyncSettings
{
    /**
     * @param  list<string>  $pushCommands  commands that push code or open a pull request
     * @param  string  $stateDirectory  absolute; where the loop guard remembers held pushes
     * @param  string  $cacheDirectory  absolute; where compiled hook patterns live
     */
    public function __construct(
        public array $pushCommands = ['git push', 'gh pr create', 'glab mr create', 'hub pull-request'],
        public bool $hooksEnabled = true,
        public ?string $defaultBranch = null,
        public string $instructionsFile = 'AGENTS.md',
        public bool $index = true,
        public int $indexMaxBytes = 8192,
        public string $stateDirectory = '',
        public string $cacheDirectory = '',
        public string $defaultSet = 'docs',
    ) {}

    public static function fromConfig(array $config, string $stateDirectory, string $cacheDirectory): self
    {
        $branch = Arr::get($config, 'instructions.default_branch');

        return new self(
            defaultSet: (string) ($config['default'] ?? 'docs'),
            pushCommands: array_values(array_filter(array_map('strval', (array) Arr::get($config, 'hooks.push_commands', [])))) ?: (new self)->pushCommands,
            hooksEnabled: (bool) Arr::get($config, 'hooks.enabled', true),
            defaultBranch: is_string($branch) && $branch !== '' ? $branch : null,
            instructionsFile: (string) Arr::get($config, 'instructions.file', 'AGENTS.md'),
            index: (bool) Arr::get($config, 'instructions.index', true),
            indexMaxBytes: max(1024, (int) Arr::get($config, 'instructions.index_max_bytes', 8192)),
            stateDirectory: $stateDirectory,
            cacheDirectory: $cacheDirectory,
        );
    }
}
