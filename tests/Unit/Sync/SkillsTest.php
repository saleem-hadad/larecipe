<?php

use LaRecipe\Domains\Sync\Actions\InstallSkillsAction;
use Symfony\Component\Yaml\Yaml;

/*
 * The four authoring skills ship to every agent (Claude Code, Codex, Cursor,
 * Copilot, Gemini CLI) through Boost, the installer and the Claude Code
 * plugin, so they must follow the portable Agent Skills spec exactly.
 */

function skillsRoot(): string
{
    return dirname(__DIR__, 3).'/resources/boost/skills';
}

dataset('skills', InstallSkillsAction::SKILLS);

it('uses only portable Agent Skills frontmatter', function (string $skill) {
    $contents = (string) file_get_contents(skillsRoot()."/{$skill}/SKILL.md");

    expect(preg_match('/\A---\n(.*?)\n---\n/s', $contents, $match))->toBe(1);

    $frontmatter = Yaml::parse($match[1]);

    expect(array_diff(array_keys($frontmatter), ['name', 'description', 'license', 'compatibility', 'metadata', 'allowed-tools']))->toBe([])
        ->and($frontmatter['name'])->toBe($skill)
        ->and($frontmatter['name'])->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')
        ->and(strlen($frontmatter['name']))->toBeLessThanOrEqual(64)
        ->and(strlen($frontmatter['description']))->toBeGreaterThan(50)->toBeLessThanOrEqual(1024)
        ->and(strlen($frontmatter['compatibility'] ?? ''))->toBeLessThanOrEqual(500)
        ->and(array_filter((array) ($frontmatter['metadata'] ?? []), fn ($v) => ! is_string($v)))->toBe([]);
})->with('skills');

it('keeps SKILL.md under 500 lines and every linked reference in place', function (string $skill) {
    $contents = (string) file_get_contents(skillsRoot()."/{$skill}/SKILL.md");

    expect(substr_count($contents, "\n"))->toBeLessThan(500);

    preg_match_all('/\]\((references\/[^)#]+)\)/', $contents, $links);

    expect($links[1])->not->toBeEmpty();

    foreach ($links[1] as $link) {
        expect(skillsRoot()."/{$skill}/{$link}")->toBeFile();
    }
})->with('skills');

it('keeps shared references identical across skills', function () {
    foreach (InstallSkillsAction::SHARED as $name => $skills) {
        $canonical = file_get_contents(skillsRoot()."/larecipe-write-docs/references/{$name}");

        foreach ($skills as $skill) {
            expect(file_get_contents(skillsRoot()."/{$skill}/references/{$name}"))->toBe($canonical, "{$skill}/references/{$name} drifted from the canonical copy");
        }
    }
});

it('tells every agent the No-Secrets Rule', function (string $skill) {
    $contents = (string) file_get_contents(skillsRoot()."/{$skill}/SKILL.md");

    expect($contents)->toMatch('/[Nn]ever (write|copy) configuration or environment values into docs/');
})->with('skills');

it('teaches the database workflow through the MCP tools, never SQL', function () {
    $reference = (string) file_get_contents(skillsRoot().'/larecipe-write-docs/references/database-sets.md');

    expect($reference)->toContain('Never write SQL')
        ->and($reference)->toContain('write_page')
        ->and($reference)->toContain('database/migrations/docs/')
        ->and($reference)->toContain('commit it with the code');
});

it('holds the four audience presets in the onboarding reference', function () {
    $onboarding = (string) file_get_contents(skillsRoot().'/larecipe-write-docs/references/onboarding.md');

    foreach (['## Product users', '## API consumers', '## Package users', '## Internal team'] as $preset) {
        expect($onboarding)->toContain($preset);
    }
});
