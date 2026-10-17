<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * The /.well-known/agent-skills/index.json document (discovery v0.2.0), plus
 * whether every caller would get the same one (so it may be cached publicly).
 */
final readonly class SkillsIndex
{
    public const SCHEMA = 'https://schemas.agentskills.io/discovery/0.2.0/schema.json';

    /**
     * @param  list<array{name: string, type: string, description: string, url: string, digest: string}>  $skills
     */
    public function __construct(
        public array $skills,
        public bool $shared,
    ) {}

    public function toArray(): array
    {
        return ['$schema' => self::SCHEMA, 'skills' => $this->skills];
    }
}
