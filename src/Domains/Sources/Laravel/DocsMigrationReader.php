<?php

namespace LaRecipe\Domains\Sources\Laravel;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Migrations\DocsMigration;
use RuntimeException;

/**
 * Reads the operations a docs migration file declares, without running them.
 *
 * The file is loaded from a fresh temporary copy each time: PHP keeps an
 * anonymous class for the life of the process under a name derived from its
 * file, so re-requiring a file the session just rewrote (the local MCP server
 * is long-lived) would return the old class.
 */
final class DocsMigrationReader
{
    public function read(string $file): DocsMigrationPlan
    {
        $copy = sys_get_temp_dir().'/larecipe-docs-migration-'.bin2hex(random_bytes(8)).'.php';
        copy($file, $copy);

        try {
            $migration = require $copy;
        } finally {
            @unlink($copy);
        }

        if (! $migration instanceof DocsMigration) {
            throw new RuntimeException("[{$file}] does not return a LaRecipe docs migration.");
        }

        $plan = $migration->plan();
        $temporaryName = pathinfo($copy, PATHINFO_FILENAME);
        $author = $plan->author;

        return new DocsMigrationPlan(
            pathinfo($file, PATHINFO_FILENAME),
            $plan->set,
            new Provenance($author->actor, $author->name, $author->ref === $temporaryName ? null : $author->ref),
            $plan->summary,
            $plan->operations,
        );
    }
}
