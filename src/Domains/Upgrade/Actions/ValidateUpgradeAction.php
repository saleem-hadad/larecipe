<?php

namespace LaRecipe\Domains\Upgrade\Actions;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Upgrade\DTOs\ConvertedPage;
use LaRecipe\Domains\Upgrade\DTOs\UpgradePlan;
use LaRecipe\Domains\Upgrade\Services\UpgradeLinkResolver;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;

/**
 * Checks the converted docs before (or after) they are written: every page
 * renders with the v3 renderer, every component is known and valid, every
 * internal link points at a converted page and, where it names one, an anchor
 * that exists. The summary goes at the end of the upgrade report.
 */
final class ValidateUpgradeAction
{
    public function __construct(
        private readonly RenderPageAction $render,
        private readonly ValidatePageAction $validate,
    ) {}

    /** @return array{pages: int, errors: int, warnings: int, issues: list<array<string, mixed>>} */
    public function execute(UpgradePlan $plan): array
    {
        $issues = [];
        $anchors = [];
        $links = [];

        foreach ($this->byVersion($plan) as $version => $pages) {
            $files = array_map(fn ($page) => $page->file, $pages);
            $resolver = new UpgradeLinkResolver($plan->project->routePrefix(), $files);

            foreach ($pages as $path => $converted) {
                $page = Page::make(new PageRef('docs', $version, 'en', $path), new Frontmatter($converted->frontmatter), $converted->body);
                $rendered = $this->render->execute($page, $resolver);
                $where = "{$version}/{$converted->file}";
                $anchors[$version][$path] = array_map(fn ($item) => $item->id, $rendered->toc);

                foreach ($rendered->issues as $issue) {
                    $issues[] = array_filter([
                        'level' => $issue['level'] ?? ValidationIssue::ERROR,
                        'rule' => $issue['rule'] ?? 'components.invalid',
                        'page' => $where,
                        'message' => $issue['message'],
                        'fix' => $issue['fix'] ?? null,
                    ]);
                }

                foreach ($this->validate->execute($page)->issues as $issue) {
                    $issues[] = ['page' => $where] + $issue->toArray();
                }
            }

            foreach ($resolver->links as $link) {
                $links[] = ['version' => $version, 'from' => "{$version}/".($files[$link['from']] ?? $link['from'])] + $link;
            }
        }

        foreach ($links as $link) {
            $targets = $anchors[$link['version']] ?? [];

            if (! isset($targets[$link['path']])) {
                $issues[] = ['level' => ValidationIssue::ERROR, 'rule' => 'links.broken', 'page' => $link['from'], 'message' => "Link to {$link['href']}: there is no page {$link['path']} in version {$link['version']}."];
            } elseif ($link['anchor'] !== null && ! in_array($link['anchor'], $targets[$link['path']], true)) {
                $issues[] = ['level' => ValidationIssue::WARNING, 'rule' => 'links.anchor', 'page' => $link['from'], 'message' => "Link to {$link['href']}: no heading with the id #{$link['anchor']} on {$link['path']}."];
            }
        }

        return [
            'pages' => count($plan->pages),
            'errors' => count(array_filter($issues, fn (array $issue) => $issue['level'] === ValidationIssue::ERROR)),
            'warnings' => count(array_filter($issues, fn (array $issue) => $issue['level'] === ValidationIssue::WARNING)),
            'issues' => $issues,
        ];
    }

    /** @return array<string, array<string, ConvertedPage>> version => path => page */
    private function byVersion(UpgradePlan $plan): array
    {
        $versions = [];

        foreach ($plan->pages as $page) {
            $versions[$page->version][$page->path] = $page;
        }

        return $versions;
    }
}
