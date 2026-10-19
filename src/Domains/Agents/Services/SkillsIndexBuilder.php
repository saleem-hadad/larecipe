<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\DTOs\SkillsIndex;

/**
 * Lists the skills of every set the caller may enter, so
 * `npx skills add https://acme.dev/docs` works for any LaRecipe site.
 *
 * Each entry's digest is the sha256 of the exact bytes served at its URL. A
 * private set appears only for a caller who may enter it, and then the index is
 * marked as not shared, so it never lands in a public cache.
 */
final class SkillsIndexBuilder
{
    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly SkillMdBuilder $skillMd,
        private readonly SkillLibrary $library,
    ) {}

    public function build(): SkillsIndex
    {
        $skills = [];
        $shared = true;

        foreach ($this->docs->sets() as $set) {
            if (! $set->config->setting('agents.skill_md', true) || ! $set->config->setting('agents.well_known_skills', true)) {
                continue;
            }

            $shared = $shared && $this->docs->isSharedView($set);

            if ($this->skillMd->override($set) === null) {
                $content = $this->skillMd->build($set);
                $skills[$this->skillMd->name($set)] ??= [
                    'name' => $this->skillMd->name($set),
                    'type' => 'skill-md',
                    'description' => $this->skillMd->description($set),
                    'url' => $this->links->skill($set),
                    'digest' => 'sha256:'.hash('sha256', $content),
                ];
            }

            foreach ($this->library->all($set) as $folder => $file) {
                $meta = $this->library->describe($file);
                $name = $meta['name'] ?? $folder;

                $skills[$name] ??= [
                    'name' => $name,
                    'type' => 'skill-md',
                    'description' => $meta['description'] ?? '',
                    'url' => $this->links->skillFile($set, $folder),
                    'digest' => 'sha256:'.hash_file('sha256', $file),
                ];
            }
        }

        return new SkillsIndex(array_values($skills), $shared);
    }
}
