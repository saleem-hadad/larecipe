<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * The page links in llms.txt resolve, and to Markdown.
 */
final class LlmsLinksCheck implements Check
{
    private const SAMPLE = 10;

    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;
        $index = $context->probe->get($context->links->llms($set, $set->isVersioned() ? $set->defaultVersion()->id : null));

        preg_match_all('/\]\((https?:\/\/[^)]+\.md)\)/', $index->body, $matches);
        $links = array_slice(array_values(array_unique($matches[1])), 0, self::SAMPLE);

        if ($links === []) {
            return DoctorCheck::warn('llms-txt-links', 'llms.txt links', 'llms.txt lists no pages.', 'Add pages to the navigation, or check that they are public.');
        }

        $broken = array_values(array_filter($links, fn (string $url) => ! $context->probe->get($url)->isMarkdown()));

        return $broken === []
            ? DoctorCheck::pass('llms-txt-links', 'llms.txt links', count($links).' sampled llms.txt links resolve to Markdown.')
            : DoctorCheck::fail('llms-txt-links', 'llms.txt links', 'These llms.txt links do not return Markdown: '.implode(', ', $broken).'.', 'Run php artisan larecipe:validate and fix the pages it reports.');
    }
}
