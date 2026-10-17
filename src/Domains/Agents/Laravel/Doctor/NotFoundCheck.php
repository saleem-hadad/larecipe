<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;
use LaRecipe\Domains\Content\DTOs\PageRef;

/**
 * A missing page is a real 404 in HTML and in Markdown, never a soft 404 an
 * agent would mistake for content.
 */
final class NotFoundCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        $set = $context->set;
        $missing = new PageRef($set->key, $set->defaultVersion()->id, $set->defaultLocale()->code, 'larecipe-doctor-missing-page');
        $html = $context->probe->get($context->links->page($set, $missing), ['Accept' => 'text/html']);
        $markdown = $context->probe->get($context->links->page($set, $missing, true));

        if ($html->status !== 404 || $markdown->status !== 404) {
            return DoctorCheck::fail('not-found', 'Missing pages', "A missing page answered {$html->status} (HTML) and {$markdown->status} (Markdown).", 'Do not catch docs 404s in the app\'s exception handler; a missing page must stay a 404.');
        }

        return str_starts_with($markdown->header('Content-Type'), 'text/markdown')
            ? DoctorCheck::pass('not-found', 'Missing pages', 'A missing page is a real 404, with a Markdown body for agents.')
            : DoctorCheck::warn('not-found', 'Missing pages', 'A missing .md page is a 404, but not in Markdown.', 'Let LaRecipe render its own 404s for docs routes.');
    }
}
