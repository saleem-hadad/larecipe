<?php

namespace LaRecipe\Domains\Delivery\Events;

use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * An agent skill was served: the generated skill.md, a hand-written skill or
 * one of its reference files, or the /.well-known/agent-skills index.
 */
final class SkillServed
{
    /**
     * @param  ?string  $set  null for the cross-set /.well-known index
     * @param  ?string  $skill  the skill name, null for the index
     */
    public function __construct(
        public readonly ?string $set,
        public readonly ?string $skill,
        public readonly string $kind,
        public readonly ?string $file = null,
        public readonly ?string $userAgent = null,
        public readonly ?PageRef $page = null,
    ) {}
}
