<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use RuntimeException;

/**
 * Adds a version to a repository set: copies an existing version's folder to
 * the new one and lists it in larecipe.json.
 *
 * A set that has no versions yet keeps its pages at the docs root. Its first
 * new version needs a name for what is there today (`$currentAs`), and those
 * pages move into that version's folder, so every version lives the same way.
 */
final class MakeVersionAction
{
    /** Files at the docs root that belong to the set, not to a version. */
    private const SET_FILES = ['larecipe.json', 'theme.css'];

    private const SET_FOLDERS = ['skills'];

    public function __construct(private readonly JsonSettings $json) {}

    /** @return list<FileChange> */
    public function execute(Workspace $workspace, CatalogSet $set, string $version, ?string $from = null, bool $default = false, ?string $currentAs = null): array
    {
        $root = rtrim((string) $set->docsPath, '/');
        $manifest = $this->json->decode($workspace->read($root.'/larecipe.json')) ?? throw new RuntimeException("{$root}/larecipe.json is not valid JSON.");
        $versions = array_values(array_filter((array) ($manifest['versions'] ?? []), 'is_array'));

        if (in_array($version, array_column($versions, 'id'), true)) {
            return [$workspace->skip($root.'/'.$version, "Version {$version} already exists.")];
        }

        $changes = [];

        if ($versions === []) {
            if ($currentAs === null || $currentAs === '' || $currentAs === $version) {
                throw new RuntimeException('This set has no versions yet. Name the version its current pages belong to with --current-as (for example --current-as=1.x).');
            }

            array_push($changes, ...$this->moveRootInto($workspace, $root, $currentAs));
            $versions[] = ['id' => $currentAs, 'default' => true];
            $from ??= $currentAs;
        }

        $from ??= $this->defaultVersion($versions);

        foreach ($this->versionFiles($workspace, $root, $from, $currentAs) as $relative => $contents) {
            $changes[] = $workspace->write("{$root}/{$version}/{$relative}", $contents);
        }

        if ($default) {
            $versions = array_map(fn (array $v) => array_diff_key($v, ['default' => true]), $versions);
        }

        $versions[] = array_filter(['id' => $version, 'default' => $default ?: null], fn ($v) => $v !== null);
        $manifest['versions'] = $versions;
        $changes[] = $workspace->write($root.'/larecipe.json', $this->json->encode($manifest));

        return $changes;
    }

    /** @return list<FileChange> */
    private function moveRootInto(Workspace $workspace, string $root, string $version): array
    {
        $changes = [];

        foreach ($workspace->files($root) as $relative) {
            $top = explode('/', $relative)[0];

            if (in_array($relative, self::SET_FILES, true) || in_array($top, self::SET_FOLDERS, true)) {
                continue;
            }

            $changes[] = $workspace->write("{$root}/{$version}/{$relative}", (string) $workspace->read("{$root}/{$relative}"));
            $changes[] = $workspace->delete("{$root}/{$relative}");
        }

        return $changes;
    }

    /**
     * A version's files. Right after a move in a dry run the files exist only
     * in the plan, so they are read from where they came from.
     *
     * @return array<string, string>
     */
    private function versionFiles(Workspace $workspace, string $root, string $from, ?string $movedFrom): array
    {
        $files = [];
        $source = $workspace->isDryRun() && $movedFrom === $from ? $root : "{$root}/{$from}";

        foreach ($workspace->files($source) as $relative) {
            $top = explode('/', $relative)[0];

            if ($source === $root && (in_array($relative, self::SET_FILES, true) || in_array($top, self::SET_FOLDERS, true))) {
                continue;
            }

            $files[$relative] = (string) $workspace->read("{$source}/{$relative}");
        }

        return $files;
    }

    private function defaultVersion(array $versions): string
    {
        foreach ($versions as $version) {
            if (($version['default'] ?? false) === true) {
                return (string) $version['id'];
            }
        }

        return (string) ($versions[0]['id'] ?? '');
    }
}
