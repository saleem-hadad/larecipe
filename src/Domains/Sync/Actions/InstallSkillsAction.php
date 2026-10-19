<?php

namespace LaRecipe\Domains\Sync\Actions;

use FilesystemIterator;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\Services\SkillReferences;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Installs LaRecipe's four authoring skills into an agent's skills folder
 * (`.claude/skills`, `.agents/skills`), with references generated from this
 * app's component registry and the JSON Schemas.
 *
 * The canonical copies live in the package (resources/boost/skills), where
 * Laravel Boost and the Claude Code plugin read them too. When Boost manages
 * the skills, `$referencesOnly` refreshes just the generated references in the
 * copies Boost already wrote, so host components still show up.
 */
final class InstallSkillsAction
{
    public const SKILLS = ['larecipe-write-docs', 'larecipe-update-docs', 'larecipe-audit-docs', 'larecipe-upgrade-v2'];

    /** Static references kept identical across skills; larecipe-write-docs holds the canonical copy. */
    public const SHARED = [
        'writing-style.md' => ['larecipe-update-docs', 'larecipe-audit-docs', 'larecipe-upgrade-v2'],
        'live-references.md' => ['larecipe-update-docs', 'larecipe-audit-docs', 'larecipe-upgrade-v2'],
        'database-sets.md' => ['larecipe-update-docs', 'larecipe-audit-docs'],
    ];

    public function __construct(
        private readonly SkillReferences $references,
        private readonly string $source,
    ) {}

    /**
     * @param  list<string>  $targets  skills folders relative to the workspace root
     * @param  bool  $canonical  the package's own copies: built-in components only, never the app's
     * @return list<FileChange>
     */
    public function execute(Workspace $workspace, array $targets, bool $referencesOnly = false, bool $canonical = false): array
    {
        $generated = $this->references->generate(builtInOnly: $canonical);
        $changes = [];

        foreach ($targets as $target) {
            foreach (self::SKILLS as $skill) {
                $directory = ltrim(rtrim($target, '/').'/'.$skill, '/');

                if ($referencesOnly && ! $workspace->exists($directory.'/SKILL.md')) {
                    continue;
                }

                if (! $referencesOnly) {
                    foreach ($this->sourceFiles($skill) as $relative => $contents) {
                        if (! isset($generated[$skill][basename($relative)]) || dirname($relative) !== 'references') {
                            $changes[] = $workspace->write($directory.'/'.$relative, $contents);
                        }
                    }
                }

                foreach ($generated[$skill] ?? [] as $name => $contents) {
                    $changes[] = $workspace->write($directory.'/references/'.$name, $contents);
                }
            }
        }

        return $changes;
    }

    /** @return array<string, string> relative path => contents, for one skill in the package */
    private function sourceFiles(string $skill): array
    {
        $directory = rtrim($this->source, '/').'/'.$skill;
        $files = [];

        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($directory))), '/')] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        // Shared references come from the canonical copy, so every skill gets the same text.
        foreach (self::SHARED as $name => $skills) {
            $canonical = rtrim($this->source, '/').'/larecipe-write-docs/references/'.$name;

            if (in_array($skill, $skills, true) && is_file($canonical)) {
                $files['references/'.$name] = (string) file_get_contents($canonical);
            }
        }

        ksort($files);

        return $files;
    }
}
