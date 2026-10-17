<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\Services\FrontmatterParser;

/**
 * The hand-written skills a set ships in its skills/{name}/SKILL.md folders,
 * served next to the docs with their reference files.
 *
 * Files are resolved with realpath() inside the skill's own folder, and dot
 * files are never served, so a request can only ever read what the folder holds.
 */
final class SkillLibrary
{
    public function __construct(
        private readonly AgentDocs $docs,
        private readonly FrontmatterParser $parser,
    ) {}

    /** @return array<string, string> folder name => absolute path of its SKILL.md */
    public function all(DocSet $set): array
    {
        $directory = $this->docs->skillsDirectory($set);

        if ($directory === null) {
            return [];
        }

        $skills = [];

        foreach (scandir($directory) ?: [] as $name) {
            if ($name[0] !== '.' && is_file($directory.'/'.$name.'/SKILL.md')) {
                $skills[$name] = $directory.'/'.$name.'/SKILL.md';
            }
        }

        ksort($skills);

        return $skills;
    }

    /** The absolute path of a file inside one skill's folder, or null when it is missing or not allowed. */
    public function file(DocSet $set, string $name, string $file): ?string
    {
        $skill = $this->all($set)[$name] ?? null;

        if ($skill === null) {
            return null;
        }

        foreach (explode('/', str_replace('\\', '/', $file)) as $segment) {
            if ($segment === '' || $segment[0] === '.') {
                return null;
            }
        }

        $base = realpath(dirname($skill));
        $path = realpath(dirname($skill).'/'.$file);

        if ($base === false || $path === false || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return null;
        }

        return $path;
    }

    /** @return array{name: ?string, description: ?string} */
    public function describe(string $skillFile): array
    {
        [$frontmatter] = $this->parser->parse((string) file_get_contents($skillFile));
        $name = $frontmatter->get('name');
        $description = $frontmatter->get('description');

        return [
            'name' => is_string($name) ? $name : null,
            'description' => is_string($description) ? trim($description) : null,
        ];
    }
}
