<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * Every sampled page has a Markdown twin at its .md URL and through
 * Accept: text/markdown, and the twin carries the same words as the HTML
 * article (the No-Cloaking Rule, measured the way AFDocs measures parity).
 */
final class MarkdownCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        if ($context->samples === []) {
            return DoctorCheck::skip('markdown', 'Markdown twins', 'There are no public pages to sample.');
        }

        $worst = 1.0;
        $worstPage = null;

        foreach ($context->samples as $ref) {
            $url = $context->links->page($context->set, $ref);
            $markdown = $context->probe->get($context->links->page($context->set, $ref, true));
            $negotiated = $context->probe->get($url, ['Accept' => 'text/markdown']);
            $html = $context->probe->get($url, ['Accept' => 'text/html']);

            if (! $markdown->isMarkdown()) {
                return DoctorCheck::fail('markdown', 'Markdown twins', "{$url}.md answered {$markdown->status} {$markdown->header('Content-Type')}.", 'Keep agents.markdown on, and check the page renders.');
            }

            if (! $negotiated->isMarkdown()) {
                return DoctorCheck::fail('markdown', 'Markdown twins', "{$url} does not answer Accept: text/markdown with Markdown.", 'Do not cache docs responses without Vary: Accept in front of the app.');
            }

            $parity = WordParity::measure($html->body, $markdown->body);

            if ($parity < $worst) {
                $worst = $parity;
                $worstPage = $url;
            }
        }

        $percent = (int) round($worst * 100);

        return match (true) {
            $worst >= 0.95 => DoctorCheck::pass('markdown', 'Markdown twins', count($context->samples)." sampled pages serve Markdown by .md and by Accept; at least {$percent}% of each article's words are in its Markdown."),
            $worst >= 0.8 => DoctorCheck::warn('markdown', 'Markdown twins', "Only {$percent}% of the words on {$worstPage} are in its Markdown.", 'Give custom components a Markdown form (LaRecipe::component(...)->markdown(...)).'),
            default => DoctorCheck::fail('markdown', 'Markdown twins', "Only {$percent}% of the words on {$worstPage} are in its Markdown.", 'Give custom components a Markdown form (LaRecipe::component(...)->markdown(...)).'),
        };
    }
}
