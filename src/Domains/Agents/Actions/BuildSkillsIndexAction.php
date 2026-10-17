<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\DTOs\SkillsIndex;
use LaRecipe\Domains\Agents\Services\SkillsIndexBuilder;

/**
 * Builds the /.well-known/agent-skills index across every set the caller may enter.
 */
final class BuildSkillsIndexAction
{
    public function __construct(private readonly SkillsIndexBuilder $builder) {}

    public function execute(): SkillsIndex
    {
        return $this->builder->build();
    }
}
