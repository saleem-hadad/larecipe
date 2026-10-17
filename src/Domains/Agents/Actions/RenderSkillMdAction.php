<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\Services\SkillMdBuilder;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Renders a set's skill.md for the current caller: the hand-written skill named
 * like the set when there is one, the generated skill otherwise.
 */
final class RenderSkillMdAction
{
    public function __construct(private readonly SkillMdBuilder $builder) {}

    public function execute(DocSet $set): string
    {
        return $this->builder->build($set);
    }
}
