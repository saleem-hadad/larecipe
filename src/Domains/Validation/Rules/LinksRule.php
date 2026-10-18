<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\LinkPaths;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * links.broken: internal links to pages or anchors that do not exist, whether
 * written as relative .md links, absolute links into any version or locale of
 * the set or #anchors on the same page. A page that is not translated yet is
 * served in its original language, so a link to it is not broken.
 * links.private-from-public: a public page linking to a private one, a link
 * every guest would follow into a 404.
 */
final class LinksRule implements PageRule
{
    public function __construct(private readonly LinkPaths $paths) {}

    public function check(PageSnapshot $page, PageContext $context): array
    {
        if ($page->rendered === null) {
            return [];
        }

        $issues = [];
        $from = $page->page->ref;
        $checked = [];

        foreach ($page->rendered->links as $link) {
            $href = $checked[] = (string) $link['href'];

            // A root .md link that spells out a page URL ("/3.x/index.md") reaches that page, as in the renderer.
            $target = (str_starts_with($href, '/') ? $this->paths->absolute($context->settings->prefix, $context->label, $href) : null)
                ?? $this->paths->relative($from->path, $page->folderIndex, $href);

            if ($target !== null) {
                $target += ['version' => $context->version, 'locale' => $context->locale];
                array_push($issues, ...$this->target($page, $context, (string) $link['href'], $target, $page->fileLine($link['line'] ?? null)));
            }
        }

        foreach (array_diff($this->hrefs($page->rendered->html), $checked) as $href) {
            if (str_starts_with($href, '#')) {
                $anchor = rawurldecode(substr($href, 1));

                if ($anchor !== '' && ! str_starts_with($anchor, 'fn') && ! in_array($anchor, $page->anchors, true)) {
                    $issues[] = $this->issue($context, ValidationIssue::error(
                        'links.broken',
                        "Link to {$href}: there is no such anchor on this page.",
                        $context->pageId($from->path),
                        $page->lineOf('('.$href) ?? $page->lineOf($href),
                        $this->anchorFix($anchor, $page->anchors),
                        $page->file,
                    ));
                }

                continue;
            }

            $target = $this->absolute($context, $href);

            if ($target !== null) {
                array_push($issues, ...$this->target($page, $context, $href, $target, $page->lineOf('('.$href) ?? $page->lineOf($href)));
            }
        }

        return $issues;
    }

    /**
     * An absolute link into any version or locale of the set ("/2.x/billing",
     * "/ar/3.x/billing"), and the page it lands on. Null when it points elsewhere.
     *
     * @return array{path: string, anchor: ?string, version: string, locale: string}|null
     */
    private function absolute(PageContext $context, string $href): ?array
    {
        $labels = $context->labels + [$context->label => [$context->version, $context->locale]];

        // Longest first: "ar/3.x" before "3.x", and an unversioned root set's "" (which matches every link) last.
        uksort($labels, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($labels as $label => [$version, $locale]) {
            $target = $this->paths->absolute($context->settings->prefix, (string) $label, $href);

            if ($target !== null) {
                return $target + ['version' => $version, 'locale' => $locale];
            }
        }

        return null;
    }

    /**
     * @param  array{path: string, anchor: ?string, version: string, locale: string}  $target
     * @return list<ValidationIssue>
     */
    private function target(PageSnapshot $page, PageContext $context, string $href, array $target, ?int $line): array
    {
        $id = $context->pageId($page->page->ref->path);
        $anchors = $context->targets->anchors($target['version'], $target['locale'], $target['path']);

        if ($anchors === null) {
            $meant = Suggest::closest($target['path'], $context->targets->paths($target['version'], $target['locale']));

            return [$this->issue($context, ValidationIssue::error(
                'links.broken',
                "Link to {$href}: there is no page at \"{$target['path']}\".",
                $id,
                $line,
                $meant !== null ? "Link to the \"{$meant}\" page instead." : 'Fix the link, or write the page it points to.',
                $page->file,
            ))];
        }

        $issues = [];

        if ($target['anchor'] !== null && ! in_array($target['anchor'], $anchors, true)) {
            $issues[] = $this->issue($context, ValidationIssue::error(
                'links.broken',
                "Link to {$href}: anchor #{$target['anchor']} not found.",
                $id,
                $line,
                $this->anchorFix($target['anchor'], $anchors),
                $page->file,
            ));
        }

        $public = ! $context->privateVersion && $page->page->frontmatter->visibility()->value !== 'private';

        if ($public && $context->targets->isPrivate($target['version'], $target['locale'], $target['path'])) {
            $issues[] = ValidationIssue::warning(
                'links.private-from-public',
                "This public page links to the private page \"{$target['path']}\"; guests get a 404 there.",
                $id,
                $line,
                'Remove the link, or make the target page public.',
                $page->file,
            );
        }

        return $issues;
    }

    /** Before a write the target may be the next page an agent writes, so link problems only warn. */
    private function issue(PageContext $context, ValidationIssue $issue): ValidationIssue
    {
        return $context->strictLinks ? $issue : $issue->asWarning();
    }

    /** @param list<string> $anchors */
    private function anchorFix(string $anchor, array $anchors): string
    {
        $meant = Suggest::closest($anchor, $anchors);

        return $meant !== null ? "Use #{$meant}." : 'Link to an existing heading, or remove the anchor.';
    }

    /** @return list<string> hrefs the author wrote that the renderer left as they were */
    private function hrefs(string $html): array
    {
        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $matches);

        return array_values(array_unique(array_map(fn (string $href) => html_entity_decode($href, ENT_QUOTES), $matches[1])));
    }
}
