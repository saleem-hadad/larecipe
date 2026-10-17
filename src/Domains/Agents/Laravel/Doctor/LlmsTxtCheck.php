<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * llms.txt exists, has the llms.txt shape (H1, blockquote, link sections) and
 * every index stays under 50,000 characters, the size agents read in one fetch.
 */
final class LlmsTxtCheck implements Check
{
    private const LIMIT = 50000;

    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;

        if (! $set->config->setting('agents.llms_txt', true)) {
            return DoctorCheck::fail('llms-txt', 'llms.txt', 'llms.txt is turned off for this set.', "Set agents.llms_txt to true for the \"{$set->key}\" set.");
        }

        $urls = [$context->links->llms($set)];

        if ($set->isVersioned()) {
            $urls[] = $context->links->llms($set, $set->defaultVersion()->id);
        }

        foreach ($urls as $url) {
            $result = $context->probe->get($url);

            if ($result->status !== 200 || ! str_starts_with($result->header('Content-Type'), 'text/')) {
                return DoctorCheck::fail('llms-txt', 'llms.txt', "{$url} answered {$result->status}.", 'Make sure the docs routes are registered and the set is public.');
            }

            if (mb_strlen($result->body) > self::LIMIT) {
                return DoctorCheck::fail('llms-txt', 'llms.txt', sprintf('%s is %s characters, over the 50,000 agents read in one fetch.', $url, number_format(mb_strlen($result->body))), 'Lower agents.llms_txt_limit so the index splits into one file per group.');
            }

            if (! str_starts_with($result->body, '# ') || ! str_contains($result->body, "\n> ") || ! str_contains($result->body, "\n## ")) {
                return DoctorCheck::warn('llms-txt', 'llms.txt', "{$url} does not follow the llms.txt shape (H1, summary, sections).", 'Add a description to larecipe.json (agents.llmsTxt.description) and pages to the navigation.');
            }
        }

        return DoctorCheck::pass('llms-txt', 'llms.txt', 'llms.txt is served and every index is under 50,000 characters.');
    }
}
