<?php

namespace LaRecipe\Domains\Sync\Actions;

use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\DTOs\SourceSuggestion;

/**
 * Adds suggested `sources:` to the frontmatter of repository pages that have
 * none yet. The edit is one inserted line, so the rest of the frontmatter
 * keeps its exact formatting and comments. Pages that already declare
 * sources, and database pages (edit those through the MCP tools), are left
 * alone.
 */
final class ApplySourcesAction
{
    /**
     * @param  list<SourceSuggestion>  $suggestions
     * @return list<FileChange>
     */
    public function execute(Workspace $workspace, array $suggestions): array
    {
        $byFile = [];

        foreach ($suggestions as $suggestion) {
            if ($suggestion->page->file !== null) {
                $byFile[$suggestion->page->file][] = $suggestion->source;
            }
        }

        $changes = [];

        foreach ($byFile as $file => $sources) {
            $contents = $workspace->read($file);

            if ($contents === null) {
                continue;
            }

            $hasFrontmatter = preg_match('/\A---\R(.*?)\R---/s', $contents, $frontmatter) === 1;

            if ($hasFrontmatter && preg_match('/^sources\s*:/m', $frontmatter[1])) {
                $changes[] = $workspace->skip($file, 'The page already declares sources.');

                continue;
            }

            $line = 'sources: '.json_encode(array_values(array_unique($sources)), JSON_UNESCAPED_SLASHES);

            $updated = $hasFrontmatter
                ? preg_replace('/\A(---\R.*?)(\R---)/s', '$1'."\n".addcslashes($line, '\\$').'$2', $contents, 1)
                : "---\n{$line}\n---\n\n".$contents;

            $changes[] = $workspace->write($file, (string) $updated);
        }

        return $changes;
    }
}
