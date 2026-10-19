<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * redirects.loop: redirects (larecipe.json redirects and frontmatter
 * redirect_from) that lead back to where they started, so a reader or an
 * agent following them never arrives.
 * redirects.shadowed: a redirect from a URL a page still lives at. Pages win,
 * so the redirect never runs.
 */
final class RedirectsRule implements SetRule
{
    public function check(DocsSnapshot $docs): array
    {
        [$edges, $pages] = $this->graph($docs);
        $issues = [];
        $reported = [];

        foreach ($edges as $from => [$to, $where]) {
            if (isset($pages[$from])) {
                $issues[] = ValidationIssue::warning('redirects.shadowed', "The redirect from {$from} never runs: the page \"{$pages[$from]}\" lives at that URL.", null, null, 'Remove the redirect, or move the page.', $where);
            }

            $chain = [$from];
            $current = $to;

            while (isset($edges[$current]) && count($chain) <= count($edges)) {
                if (in_array($current, $chain, true)) {
                    $loop = array_slice($chain, (int) array_search($current, $chain, true));
                    $key = implode('|', $this->sorted($loop));

                    if (! isset($reported[$key])) {
                        $reported[$key] = true;
                        $issues[] = ValidationIssue::error('redirects.loop', 'These redirects form a loop: '.implode(' -> ', [...$loop, $current]).'.', null, null, 'Point one of them at a page instead of another redirect.', $where);
                    }

                    break;
                }

                $chain[] = $current;
                $current = $edges[$current][0];
            }

            if ($from === $to && ! isset($reported[$from])) {
                $reported[$from] = true;
                $issues[] = ValidationIssue::error('redirects.loop', "The redirect from {$from} points at itself.", null, null, 'Point it at the page that replaced it.', $where);
            }
        }

        return $issues;
    }

    /** @return array{0: array<string, array{0: string, 1: string}>, 1: array<string, string>} redirects from => [to, file], and page URL => page id */
    private function graph(DocsSnapshot $docs): array
    {
        $edges = [];
        $pages = [];

        foreach ((array) ($docs->manifest['redirects'] ?? []) as $redirect) {
            if (is_array($redirect) && isset($redirect['from'], $redirect['to'])) {
                $edges[$this->normalize((string) $redirect['from'])] = [$this->normalize((string) $redirect['to']), $docs->manifestFile];
            }
        }

        foreach ($docs->versions as $version) {
            foreach ($version->pages as $path => $page) {
                $url = $this->url($docs->settings->prefix, $version->label, $path);
                $pages[$url] = $version->pageId($path);

                foreach ($page->page->frontmatter->redirectFrom() as $from) {
                    $edges[$this->normalize($from)] ??= [$url, (string) $page->file];
                }
            }
        }

        return [$edges, $pages];
    }

    private function url(string $prefix, string $label, string $path): string
    {
        $segments = array_filter([trim($prefix, '/'), $label, $path === 'index' ? '' : $path], fn (string $s) => $s !== '');

        return '/'.implode('/', $segments);
    }

    private function normalize(string $url): string
    {
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        return '/'.trim((string) preg_replace('/[?#].*$/', '', $url), '/');
    }

    /** @param list<string> $items @return list<string> */
    private function sorted(array $items): array
    {
        sort($items);

        return $items;
    }
}
