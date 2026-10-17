<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * skill.md is served with portable Agent Skills frontmatter (a valid name and
 * a description), so skill installers and every agent can load it.
 */
final class SkillCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;

        if (! $set->config->setting('agents.skill_md', true)) {
            return DoctorCheck::warn('skill-md', 'skill.md', 'skill.md is turned off, so agents cannot learn how to use the product from the docs.', "Set agents.skill_md to true for the \"{$set->key}\" set.");
        }

        $skill = $context->probe->get($context->links->skill($set));

        if (! $skill->isMarkdown()) {
            return DoctorCheck::fail('skill-md', 'skill.md', "skill.md answered {$skill->status}.", 'Check the docs routes are registered.');
        }

        try {
            $frontmatter = preg_match('/\A---\n(.*?)\n---\n/s', $skill->body, $matches) ? (array) Yaml::parse($matches[1]) : [];
        } catch (ParseException) {
            $frontmatter = [];
        }

        $name = $frontmatter['name'] ?? null;

        if (! is_string($name) || ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) || empty($frontmatter['description'])) {
            return DoctorCheck::fail('skill-md', 'skill.md', 'skill.md has no valid name and description in its frontmatter.', 'Fix the hand-written skill that replaces it; php artisan larecipe:validate reports skills.spec problems.');
        }

        return DoctorCheck::pass('skill-md', 'skill.md', "skill.md is served as the \"{$name}\" skill.");
    }
}
