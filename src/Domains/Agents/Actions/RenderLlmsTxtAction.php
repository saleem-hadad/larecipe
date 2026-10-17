<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\Services\LlmsTxtBuilder;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Renders one llms.txt file of a set for the current caller: the set root, a
 * version index or a group's path-scoped file. Null means "not found for this
 * caller", which every surface answers with a 404.
 *
 * Static export and the doctor call this too, so they write exactly what the
 * site serves.
 */
final class RenderLlmsTxtAction
{
    public function __construct(private readonly LlmsTxtBuilder $builder) {}

    public function execute(DocSet $set, ?string $version = null, ?string $locale = null, ?string $group = null): ?string
    {
        $limit = (int) $set->config->setting('agents.llms_txt_limit', LlmsTxtBuilder::DEFAULT_LIMIT);
        $locale ??= $set->defaultLocale()->code;

        if ($version === null) {
            return $group === null ? $this->builder->root($set, $locale, $limit) : null;
        }

        return $group === null
            ? $this->builder->version($set, $version, $locale, $limit)
            : $this->builder->group($set, $version, $locale, $group, $limit);
    }
}
