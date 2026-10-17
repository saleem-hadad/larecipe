<?php

namespace LaRecipe\Domains\Sync\Contracts;

/**
 * Answers "which file defines this?" for names that appear in docs: classes,
 * artisan commands and route names. Used by `larecipe:sources:suggest`.
 *
 * Every answer is a path relative to the project root, or null when the name
 * is unknown or defined outside the project (a vendor class, a framework
 * command). It never reads configuration or environment values.
 */
interface Codebase
{
    public function classFile(string $class): ?string;

    public function commandFile(string $command): ?string;

    public function routeFile(string $name): ?string;
}
