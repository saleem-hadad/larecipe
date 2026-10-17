<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\Services\LlmsFullBuilder;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Renders a version's llms-full.txt (or one group's, when the version is split)
 * as a list of chunks to stream. Null means "not found for this caller".
 */
final class RenderLlmsFullAction
{
    public function __construct(private readonly LlmsFullBuilder $builder) {}

    /** @return list<string>|null */
    public function execute(DocSet $set, string $version, ?string $locale = null, ?string $group = null): ?array
    {
        return $this->builder->build(
            $set,
            $version,
            $locale ?? $set->defaultLocale()->code,
            $group,
            (int) $set->config->setting('agents.llms_full_limit', LlmsFullBuilder::DEFAULT_LIMIT),
        );
    }
}
