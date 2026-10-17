<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

use LaRecipe\Domains\Agents\DTOs\DoctorCheck;

/**
 * HTML pages point agents at their agent surfaces: Link headers to the
 * Markdown twin and llms.txt, Vary: Accept for caches, and a visible
 * llms.txt link in the page body (what AFDocs looks for in the HTML).
 */
final class DiscoveryCheck implements Check
{
    public function run(DoctorContext $context): DoctorCheck
    {
        $page = $context->landing();

        if ($page->status !== 200) {
            return DoctorCheck::fail('discovery', 'Discovery hints', "The landing page answered {$page->status}.", 'Check the set\'s landing page exists and is public.');
        }

        $link = $page->header('Link');
        $missing = array_filter([
            str_contains($link, 'rel="alternate"; type="text/markdown"') ? null : 'a Link to the Markdown twin',
            str_contains($link, 'llms.txt>; rel="describedby"') ? null : 'a Link to llms.txt',
            str_contains($page->header('Vary'), 'Accept') ? null : 'Vary: Accept',
        ]);

        if ($missing !== []) {
            return DoctorCheck::fail('discovery', 'Discovery hints', 'The HTML response is missing '.implode(', ', $missing).'.', 'Make sure nothing in front of the app strips Link and Vary headers.');
        }

        $body = preg_match('/<body[^>]*>(.*)<\/body>/s', $page->body, $matches) ? $matches[1] : $page->body;
        $body = (string) preg_replace('/<(nav|script|style)\b.*?<\/\1>/s', '', $body);

        return str_contains($body, '/llms.txt')
            ? DoctorCheck::pass('discovery', 'Discovery hints', 'Pages send Link headers to their Markdown twin and llms.txt, and link to llms.txt in the page.')
            : DoctorCheck::warn('discovery', 'Discovery hints', 'Pages send Link headers, but the HTML body has no link to llms.txt.', 'Add a visually hidden link to llms.txt near the top of the page shell, outside <nav>.');
    }
}
