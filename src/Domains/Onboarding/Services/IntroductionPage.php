<?php

namespace LaRecipe\Domains\Onboarding\Services;

use LaRecipe\Domains\Onboarding\DTOs\AppFacts;

/**
 * The first page of a new doc set, written from facts the installer read:
 * the app's name, its Laravel version, its first-party packages and the
 * opening of its README. Short, true and never lorem ipsum. Its sources are
 * the README and composer.json, so it shows up as stale when they change.
 */
final class IntroductionPage
{
    public function __construct(private readonly FrontmatterWriter $frontmatter) {}

    /** The page as a Markdown file, for repository sets. */
    public function render(AppFacts $facts): string
    {
        return rtrim($this->frontmatter->render($this->frontmatter($facts)))."\n\n".$this->body($facts);
    }

    /** @return array{title: string, description: string, sources: list<string>} */
    public function frontmatter(AppFacts $facts): array
    {
        return [
            'title' => 'Introduction',
            'description' => $this->description($facts),
            'sources' => array_values(array_filter([$facts->readmeIntro !== null ? 'README.md' : null, 'composer.json'])),
        ];
    }

    /** The Markdown after the frontmatter, for database sets (a docs migration carries it). */
    public function body(AppFacts $facts): string
    {
        $lines = ['# '.$facts->name, '', $facts->readmeIntro ?? $this->description($facts), ''];

        $stack = $this->stack($facts);

        if ($stack !== null) {
            array_push($lines, '## Built with', '', $stack, '');
        }

        return implode("\n", $lines);
    }

    private function description(AppFacts $facts): string
    {
        $source = $facts->readmeIntro ?? $facts->description;

        if ($source !== null && preg_match('/^(.+?[.!?])(\s|$)/s', preg_replace('/\s+/', ' ', strip_tags($source)) ?? $source, $match)) {
            $sentence = trim(preg_replace('/[*_`]/', '', $match[1]) ?? $match[1]);

            if (mb_strlen($sentence) <= 200) {
                return $sentence;
            }
        }

        return "Documentation for {$facts->name}.";
    }

    private function stack(AppFacts $facts): ?string
    {
        if ($facts->laravelVersion === null && $facts->packages === []) {
            return null;
        }

        $lines = [];

        if ($facts->laravelVersion !== null) {
            $major = explode('.', $facts->laravelVersion)[0];
            // A fresh skeleton is still named "Laravel"; "Laravel is a Laravel 13 application" reads as a typo.
            $subject = $facts->name === 'Laravel' ? 'This' : $facts->name;
            $lines[] = "{$subject} is a Laravel {$major} application".($facts->php !== null ? " (PHP {$facts->php})" : '').'.';
        }

        if ($facts->packages !== []) {
            $lines[] = '';
            $lines[] = 'It uses:';
            $lines[] = '';

            foreach ($facts->packages as $package) {
                $lines[] = "- `{$package}`";
            }
        }

        return implode("\n", $lines);
    }
}
