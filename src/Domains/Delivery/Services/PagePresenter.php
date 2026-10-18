<?php

namespace LaRecipe\Domains\Delivery\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Delivery\Support\Freshness;
use LaRecipe\Domains\Delivery\Support\InsertRenderer;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
use LaRecipe\Domains\Markdown\DTOs\TocItem;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\LaRecipe;

/**
 * Shapes a rendered page for each audience: the Blade prerender, the boot
 * payload React reads, and the JSON payload for client-side navigation.
 *
 * All three come from the same data, so the server HTML and the React UI can
 * never disagree about what is on the page. Anything a reader sees as text
 * (relative times, the breadcrumb, the Last change ticket, UI strings) is
 * worded here once, in the page's locale, for the same reason: when React
 * takes over, not a single word may change.
 */
final class PagePresenter
{
    /** Words per minute for the reading time in the provenance line. */
    private const WORDS_PER_MINUTE = 220;

    /** The insert points a page can carry, in the order they appear. */
    public const INSERT_POINTS = ['header.actions', 'sidebar.before', 'sidebar.after', 'article.before', 'article.after', 'toc.after', 'footer'];

    /** @var list<\Closure(DocSet, array): array> */
    private array $extenders = [];

    /** @var array<string, array<string, string>> */
    private array $strings = [];

    public function __construct(
        private readonly DocsUrls $urls,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly SourceResolver $sources,
        private readonly Assets $assets,
        private readonly LaRecipe $larecipe,
        private readonly Translator $translator,
        private readonly InsertRenderer $inserts,
        private readonly Freshness $freshness,
    ) {}

    /**
     * Let other domains add to the boot payload (search index URL, Ask AI,
     * feedback endpoint) without Delivery depending on them.
     *
     * @param  \Closure(DocSet, array): array  $extender  receives the payload, returns additions
     */
    public function extend(\Closure $extender): void
    {
        $this->extenders[] = $extender;
    }

    /**
     * @param  array<string, PageSummary>  $visible  the reader's visible pages in this version and locale
     */
    public function present(DocSet $set, Page $page, RenderedPage $rendered, array $visible, ?string $fallbackFromLocale = null): array
    {
        // A fallback page keeps the reader in their language: the chrome, navigation and
        // links stay in the requested locale, only the article is in the original one.
        $ref = $page->ref;
        $reader = $fallbackFromLocale !== null ? $ref->withLocale($fallbackFromLocale) : $ref;
        $navigation = $this->navigation($set, $reader, $visible);
        $sequence = $this->catalog->sequence($navigation);
        $position = array_search($ref->path, $sequence, true);
        $locale = $reader->locale;
        $provenance = $set->manifest->showsProvenance() ? $page->provenance?->toArray() : null;
        $markdownUrl = $this->urls->page($set, $ref, true);

        $payload = [
            'set' => $this->set($set, $reader),
            'page' => [
                'path' => $ref->path,
                'title' => $page->title,
                'description' => $page->description,
                'url' => $this->urls->page($set, $ref),
                'path_url' => $this->urls->pagePath($set, $reader),
                'alternates' => $this->alternates($set, $ref),
                'markdown_url' => $markdownUrl,
                'edit_url' => $this->editUrl($set, $ref),
                'updated_at' => $page->updatedAt?->format(DATE_ATOM),
                'updated_human' => $page->updatedAt !== null ? $this->relativeTime($page->updatedAt, $locale) : null,
                'fresh' => $this->freshness->isFresh($page->updatedAt),
                'provenance' => $provenance,
                'ticket' => null,
                'breadcrumb' => $this->breadcrumb($navigation, $ref->path),
                'reading_minutes' => $minutes = $this->readingMinutes($rendered->html),
                'reading_label' => $this->translator->choice('larecipe::ui.page.reading', $minutes, ['count' => $minutes], $locale),
                'layout' => $page->frontmatter->layout(),
                'badge' => $page->frontmatter->get('badge'),
                'meta' => $page->frontmatter->meta(),
                'inserts' => $page->frontmatter->inserts(),
                'toc' => array_map(fn (TocItem $item) => $item->toArray(), $rendered->toc),
                'components' => $rendered->components,
                'previous' => $position !== false && $position > 0 ? $this->neighbour($set, $reader, $sequence[$position - 1], $visible) : null,
                'next' => $position !== false && isset($sequence[$position + 1]) ? $this->neighbour($set, $reader, $sequence[$position + 1], $visible) : null,
                'fallback_from_locale' => $fallbackFromLocale,
                'fallback_notice' => $fallbackFromLocale !== null ? $this->fallbackNotice($set, $locale, $ref->locale) : null,
                'content_locale' => [
                    'code' => $ref->locale,
                    'dir' => ($set->locale($ref->locale) ?? $set->defaultLocale())->dir->value,
                ],
                'noindex' => $page->frontmatter->noindex(),
                'actions' => $this->agentActions($set, $markdownUrl, $locale),
            ],
            'navigation' => $navigation,
            'version' => $ref->version,
            'locale' => [
                'code' => $reader->locale,
                'dir' => ($set->locale($reader->locale) ?? $set->defaultLocale())->dir->value,
            ],
            'urls' => [
                'base' => $this->urls->base($set),
                'llms' => $this->urls->llms($set, $set->isVersioned() ? $ref->version : null),
                'mcp' => $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null,
                'skill' => $set->config->setting('agents.skill_md', true) ? $this->urls->skill($set) : null,
            ],
            'features' => [
                'search' => (string) $set->config->setting('search.driver', 'client'),
                'ask' => false,
                'feedback' => false,
            ],
            'inserts' => $set->manifest->inserts(),
            'user' => $this->larecipe->sharedUser($this->access->user()),
            'build' => $this->assets->build(),
            'extension' => ['enabled' => $this->assets->extensionEntry() !== null],
            'strings' => $this->strings($locale),
        ];

        foreach ($this->extenders as $extender) {
            $payload = array_replace_recursive($payload, $extender($set, $payload));
        }

        // Worded last, so provenance other domains added (a ticket number, a
        // summary, lines changed) reaches the ticket.
        $payload['page']['ticket'] = is_array($payload['page']['provenance'])
            ? $this->ticket($set, $payload['page']['provenance'], $locale)
            : null;

        return $payload;
    }

    /**
     * The JSON a client-side navigation fetches: the article and what changes
     * around it. Navigation is included so a version switch can redraw it, and
     * the insert points' fallbacks so components only PHP knows keep rendering.
     */
    public function json(array $payload, RenderedPage $rendered): array
    {
        return [
            'page' => $payload['page'] + ['html' => $rendered->html],
            'navigation' => $payload['navigation'],
            'version' => $payload['version'],
            'locale' => $payload['locale'],
            'set' => $payload['set'],
            'urls' => $payload['urls'],
            'build' => $payload['build'],
            'inserts_html' => $this->insertsHtml($payload),
        ];
    }

    /**
     * The server fallback HTML of every insert point on this page, honoring the
     * page's opt-outs (frontmatter `inserts: false` or `inserts: {point: false}`).
     *
     * @return array<string, string>
     */
    public function insertsHtml(array $payload): array
    {
        $html = [];
        $optOut = $payload['page']['inserts'] ?? [];

        foreach (self::INSERT_POINTS as $point) {
            $names = $payload['inserts'][$point] ?? [];

            $html[$point] = $names === [] || $optOut === false || (($optOut[$point] ?? true) === false)
                ? ''
                : $this->inserts->render($names, $payload);
        }

        return $html;
    }

    /**
     * Every UI string for a locale, flattened to dot keys ("search.label").
     * Missing keys fall back to English, so a partial translation still works.
     *
     * @return array<string, string>
     */
    public function strings(string $locale): array
    {
        if (isset($this->strings[$locale])) {
            return $this->strings[$locale];
        }

        $english = $this->translator->get('larecipe::ui', [], 'en');
        $translated = $locale === 'en' ? [] : $this->translator->get('larecipe::ui', [], $locale);

        $merged = array_replace_recursive(
            is_array($english) ? $english : [],
            is_array($translated) ? $translated : [],
        );

        return $this->strings[$locale] = array_map('strval', Arr::dot($merged));
    }

    private function set(DocSet $set, PageRef $ref): array
    {
        $manifest = $set->manifest;

        return [
            'key' => $set->key,
            'name' => $manifest->name,
            'description' => $manifest->description,
            'base_path' => $set->config->basePath(),
            'logo' => $manifest->logo(),
            'favicon' => $manifest->favicon(),
            'theme' => ['mode' => $manifest->theme->mode],
            'navbar' => $manifest->navbar(),
            'footer' => $manifest->footer(),
            'credit' => (bool) $manifest->get('credit', true),
            'repository' => $manifest->repository()['url'],
            'versions' => array_map(fn ($version) => $version->toArray() + [
                'url' => $this->switchUrl($set, $ref->withVersion($version->id)),
            ], $this->readableVersions($set)),
            'locales' => array_map(fn ($locale) => $locale->toArray() + [
                'url' => $this->switchUrl($set, $ref->withLocale($locale->code)),
                'translated' => $this->readable($set, $this->catalog->summaries($set, $ref->version, $locale->code)[$ref->path] ?? null),
            ], $set->isMultilingual() ? $set->locales() : []),
            'integrations' => $set->manifestIntegrations(),
        ];
    }

    /**
     * The versions the version switcher offers: only those the reader may
     * read, so a guest never learns a private version's id or label.
     *
     * @return list<Version>
     */
    private function readableVersions(DocSet $set): array
    {
        $versions = $set->isVersioned() ? $set->versions() : [];

        return array_values(array_filter($versions, fn (Version $version) => $this->access->canViewVersion($set, $version->id)));
    }

    /**
     * True when the page exists and the reader may see it. The switchers and
     * hreflang alternates ask this, so they reveal no more than the pages do.
     */
    private function readable(DocSet $set, ?PageSummary $page): bool
    {
        return $page !== null && $this->access->canView($set, $page);
    }

    /**
     * The same page in another version or locale, or that version's landing page
     * when the reader cannot read it there. An untranslated page still exists in
     * every locale (it falls back to the original), so switching language keeps the page.
     */
    private function switchUrl(DocSet $set, PageRef $ref): string
    {
        $page = $this->catalog->localized($set, $ref->version, $ref->locale)[$ref->path] ?? null;

        return $this->readable($set, $page)
            ? $this->urls->pagePath($set, $ref)
            : $this->urls->pagePath($set, $ref->withPath($set->landingPath()));
    }

    /**
     * hreflang alternates: the page in every locale it is written in and the
     * reader may read, plus x-default for the original. Fallbacks are not
     * alternates: they are the original, which is the canonical URL.
     *
     * @return list<array{hreflang: string, url: string}>
     */
    private function alternates(DocSet $set, PageRef $ref): array
    {
        if (! $set->isMultilingual()) {
            return [];
        }

        $alternates = [];

        foreach ($set->locales() as $locale) {
            if ($this->readable($set, $this->catalog->summaries($set, $ref->version, $locale->code)[$ref->path] ?? null)) {
                $alternates[] = ['hreflang' => $locale->code, 'url' => $this->urls->page($set, $ref->withLocale($locale->code))];
            }
        }

        $alternates[] = ['hreflang' => 'x-default', 'url' => $this->urls->page($set, $ref->withLocale($set->defaultLocale()->code))];

        return $alternates;
    }

    /**
     * Navigation as the reader sees it, with URLs, the active page, the fresh
     * dot for other pages changed within larecipe.ui.fresh_days (the
     * sidebar's only decoration, and it is a state) and the fallback flag for
     * pages shown in the original language. A fresh page carries its
     * updated_at, so the browser can hide the dot once the reader has opened
     * that version of the page (resources/views/partials/head.blade.php).
     *
     * @param  array<string, PageSummary>  $visible
     */
    private function navigation(DocSet $set, PageRef $ref, array $visible): array
    {
        $tabs = $this->catalog->navigation($set, $ref->version, $ref->locale, $visible);

        $withUrls = function (array $items) use (&$withUrls, $set, $ref, $visible): array {
            return array_map(function (array $item) use (&$withUrls, $set, $ref, $visible) {
                if ($item['type'] === 'page') {
                    $item['url'] = $this->urls->pagePath($set, $ref->withPath($item['path']));
                    $item['active'] = $item['path'] === $ref->path;
                    $updatedAt = $visible[$item['path']]->updatedAt ?? null;
                    $item['fresh'] = ! $item['active'] && $this->freshness->isFresh($updatedAt);

                    if ($item['fresh']) {
                        $item['updated_at'] = $updatedAt?->format(DATE_ATOM);
                    }

                    $item['fallback'] = isset($visible[$item['path']]) && $visible[$item['path']]->ref->locale !== $ref->locale;
                } elseif ($item['type'] === 'group') {
                    $item['items'] = $withUrls($item['items']);
                }

                return $item;
            }, $items);
        };

        return array_map(fn (array $tab) => ['items' => $withUrls($tab['items'])] + $tab, $tabs);
    }

    /**
     * The group labels above the current page ("Get started / Installation").
     * Empty for a page that sits at the top of the navigation, where the label
     * would only repeat the title.
     *
     * @return list<string>
     */
    private function breadcrumb(array $navigation, string $path): array
    {
        $find = function (array $items, array $trail) use (&$find, $path): ?array {
            foreach ($items as $item) {
                if ($item['type'] === 'page' && $item['path'] === $path) {
                    return $trail;
                }

                if ($item['type'] === 'group' && ($found = $find($item['items'], [...$trail, $item['label']])) !== null) {
                    return $found;
                }
            }

            return null;
        };

        foreach ($navigation as $tab) {
            $trail = $find($tab['items'], $tab['label'] !== null && $tab['label'] !== '' ? [$tab['label']] : []);

            if ($trail !== null) {
                return array_values(array_filter($trail, fn ($label) => is_string($label) && $label !== ''));
            }
        }

        return [];
    }

    /**
     * The Last change ticket, worded once on the server. A ticket only exists
     * when the source recorded a real change (provenance); it is never drawn for
     * a page that merely has a file date.
     *
     * The number is the source's revision or ticket number when it has one,
     * otherwise the short commit hash stands in.
     */
    private function ticket(DocSet $set, array $provenance, string $locale): array
    {
        $ref = isset($provenance['ref']) ? (string) $provenance['ref'] : null;
        $isHash = $ref !== null && preg_match('/^[0-9a-f]{7,40}$/i', $ref) === 1;
        $at = isset($provenance['at']) ? new DateTimeImmutable((string) $provenance['at']) : null;
        $name = isset($provenance['name']) && $provenance['name'] !== '' ? (string) $provenance['name'] : null;
        $pages = isset($provenance['pages']) ? (int) $provenance['pages'] : null;

        $url = $provenance['url'] ?? null;
        $repository = $set->manifest->repository()['url'];

        if ($url === null && $isHash && $repository !== null) {
            $url = "{$repository}/commit/{$ref}";
        }

        return [
            'number' => isset($provenance['ticket']) ? (string) $provenance['ticket'] : null,
            'ref' => $isHash ? substr((string) $ref, 0, 7) : $ref,
            'at' => $at?->format(DATE_ATOM),
            'time' => $at !== null ? $this->ticketTime($at, $locale) : null,
            'summary' => isset($provenance['summary']) && $provenance['summary'] !== ''
                ? (string) $provenance['summary']
                : ($name !== null
                    ? $this->translator->get('larecipe::ui.ticket.summary_by', ['name' => $name], $locale)
                    : $this->translator->get('larecipe::ui.ticket.summary', [], $locale)),
            'actor' => $provenance['actor'] ?? null,
            'name' => $name,
            'approved_by' => $provenance['approved_by'] ?? null,
            'pages' => $pages,
            'pages_label' => $pages !== null ? $this->translator->choice('larecipe::ui.ticket.pages', $pages, ['count' => $pages], $locale) : null,
            'additions' => isset($provenance['additions']) ? (int) $provenance['additions'] : null,
            'deletions' => isset($provenance['deletions']) ? (int) $provenance['deletions'] : null,
            'url' => $url,
        ];
    }

    /**
     * The notice on a page shown in its original language: worded in the
     * reader's language, and repeated in the original's, since that is the
     * language the rest of the page is in.
     *
     * @return array{text: string, original: ?string, original_locale: string, original_dir: string}
     */
    private function fallbackNotice(DocSet $set, string $readerLocale, string $contentLocale): array
    {
        $text = $this->strings($readerLocale)['page.fallback'] ?? '';
        $original = $this->strings($contentLocale)['page.fallback'] ?? null;

        return [
            'text' => $text,
            'original' => $original !== $text ? $original : null,
            'original_locale' => $contentLocale,
            'original_dir' => ($set->locale($contentLocale) ?? $set->defaultLocale())->dir->value,
        ];
    }

    /**
     * The "For agents" page actions: open the page's Markdown in an AI tool with
     * a short prompt, and the command that connects this set's MCP server. Six
     * destinations at most, never eighteen.
     *
     * @return array{prompt: string, claude: string, chatgpt: string, cursor: string, mcp_command: ?string}
     */
    private function agentActions(DocSet $set, string $markdownUrl, string $locale): array
    {
        $prompt = $this->translator->get('larecipe::ui.actions.prompt', ['url' => $markdownUrl], $locale);
        $query = rawurlencode($prompt);
        $mcp = $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null;
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($set->manifest->name)), '-') ?: $set->key;

        return [
            'prompt' => $prompt,
            'claude' => "https://claude.ai/new?q={$query}",
            'chatgpt' => "https://chatgpt.com/?q={$query}",
            'cursor' => "https://cursor.com/link/prompt?text={$query}",
            'mcp_command' => $mcp !== null ? "claude mcp add --transport http {$name} {$mcp}" : null,
        ];
    }

    /** "14:02" for a change made today, "12 Oct" otherwise; numerals stay Western in every locale. */
    private function ticketTime(DateTimeInterface $at, string $locale): string
    {
        $time = Carbon::instance($at)->setTimezone(date_default_timezone_get())->locale($locale);

        return $time->isToday() ? $time->format('H:i') : $time->isoFormat('D MMM');
    }

    private function relativeTime(DateTimeInterface $at, string $locale): string
    {
        return Carbon::instance($at)->locale($locale)->diffForHumans();
    }

    private function readingMinutes(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    private function neighbour(DocSet $set, PageRef $ref, string $path, array $visible): ?array
    {
        $summary = $visible[$path] ?? null;

        return $summary === null ? null : [
            'title' => $summary->title,
            'url' => $this->urls->pagePath($set, $ref->withPath($path)),
        ];
    }

    private function editUrl(DocSet $set, PageRef $ref): ?string
    {
        $repository = $set->manifest->repository();
        $source = $this->sources->for($set->key);

        if ($repository['url'] === null || ! $source instanceof RepositorySource) {
            return null;
        }

        $file = $source->pageFile($ref);
        $root = realpath($source->root());

        if ($file === null || $root === false) {
            return null;
        }

        $relative = ltrim(substr((string) realpath($file), strlen($root)), '/');

        return "{$repository['url']}/edit/{$repository['branch']}/{$repository['docsPath']}/{$relative}";
    }
}
