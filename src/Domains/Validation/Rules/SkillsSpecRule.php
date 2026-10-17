<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\SkillSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * skills.spec: hand-written skills in skills/{name}/SKILL.md that break the
 * Agent Skills spec. Only the portable frontmatter keys are allowed, so every
 * agent (and every skill installer) can load the skill, not just one vendor's.
 */
final class SkillsSpecRule implements SetRule
{
    public const ALLOWED_KEYS = ['name', 'description', 'license', 'compatibility', 'metadata', 'allowed-tools'];

    private const MAX_LINES = 500;

    public function __construct(private readonly FrontmatterParser $parser) {}

    public function check(DocsSnapshot $docs): array
    {
        $issues = [];

        foreach ($docs->skills as $skill) {
            array_push($issues, ...$this->skill($skill));
        }

        return $issues;
    }

    /** @return list<ValidationIssue> */
    private function skill(SkillSnapshot $skill): array
    {
        [$frontmatter, $body, $yamlError] = $this->parser->parse($skill->contents);
        $data = $frontmatter->toArray();
        $file = $skill->file;

        if ($yamlError !== null) {
            return [ValidationIssue::error('skills.spec', 'The skill\'s frontmatter is not valid YAML: '.$yamlError, null, 1, 'Fix the YAML between the --- lines.', $file)];
        }

        if ($data === []) {
            return [ValidationIssue::error('skills.spec', 'The skill has no frontmatter.', null, 1, "Start SKILL.md with ---, name: {$skill->folder}, a description, and ---.", $file)];
        }

        $issues = [];
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;

        if (! is_string($name) || $name === '') {
            $issues[] = ValidationIssue::error('skills.spec', 'The skill has no name.', null, null, "Add name: {$skill->folder}.", $file);
        } elseif (strlen($name) > 64 || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name)) {
            $issues[] = ValidationIssue::error('skills.spec', "The skill name \"{$name}\" is not valid: use 1 to 64 lowercase letters, digits and single hyphens.", null, null, 'Rename the skill and its folder.', $file);
        } elseif ($name !== $skill->folder) {
            $issues[] = ValidationIssue::error('skills.spec', "The skill is named \"{$name}\" but its folder is \"{$skill->folder}\".", null, null, "Rename the folder to {$name}, or the skill to {$skill->folder}.", $file);
        }

        if (! is_string($description) || trim($description) === '') {
            $issues[] = ValidationIssue::error('skills.spec', 'The skill has no description.', null, null, 'Add a description that says what the skill does and when to use it.', $file);
        } elseif (mb_strlen($description) > 1024) {
            $issues[] = ValidationIssue::error('skills.spec', 'The skill description is longer than 1,024 characters.', null, null, 'Shorten the description.', $file);
        }

        if (isset($data['compatibility']) && (! is_string($data['compatibility']) || mb_strlen($data['compatibility']) > 500)) {
            $issues[] = ValidationIssue::error('skills.spec', 'compatibility must be text of at most 500 characters.', null, null, null, $file);
        }

        if (isset($data['metadata']) && (! is_array($data['metadata']) || array_filter($data['metadata'], fn ($value, $key) => ! is_string($key) || ! is_scalar($value), ARRAY_FILTER_USE_BOTH) !== [])) {
            $issues[] = ValidationIssue::error('skills.spec', 'metadata must map names to text values.', null, null, null, $file);
        }

        foreach (array_diff(array_keys($data), self::ALLOWED_KEYS) as $key) {
            $issues[] = ValidationIssue::error('skills.spec', "The frontmatter key \"{$key}\" is not part of the Agent Skills spec, so some agents will refuse the skill.", null, null, 'Allowed keys: '.implode(', ', self::ALLOWED_KEYS).'. Move anything else into the body.', $file);
        }

        $lines = substr_count($skill->contents, "\n") + 1;

        if ($lines > self::MAX_LINES) {
            $issues[] = ValidationIssue::warning('skills.spec', "SKILL.md is {$lines} lines; agents load all of it.", null, null, 'Keep SKILL.md under 500 lines and move details into references/ files.', $file);
        }

        return $issues;
    }
}
