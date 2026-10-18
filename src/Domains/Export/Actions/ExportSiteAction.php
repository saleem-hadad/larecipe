<?php

namespace LaRecipe\Domains\Export\Actions;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\Enums\Visibility;
use LaRecipe\Domains\Export\Contracts\SiteClient;
use LaRecipe\Domains\Export\DTOs\ExportOptions;
use LaRecipe\Domains\Export\DTOs\ExportResult;
use LaRecipe\Domains\Export\Services\ExportFiles;
use LaRecipe\Domains\Export\Services\StaticHtml;
use LaRecipe\Domains\Sources\Actions\ListLocalizedPagesAction;

/**
 * Exports doc sets as a static site: every page a guest can read, as HTML and as
 * its Markdown twin, plus llms.txt, llms-full.txt, skill.md, the sitemap, the
 * guest search index, the built UI and the docs' own assets.
 *
 * Pages are fetched as a guest from the site itself, so the export is exactly
 * what the server serves a signed-out reader: private sets, versions and pages
 * stay out, gate blocks render for guests, and live references resolve now.
 * What a static host cannot offer (Ask AI, MCP, feedback) is listed, not faked.
 */
final class ExportSiteAction
{
    /** Agent surfaces, per set and per version, exported when the site serves them. */
    private const SET_FILES = ['llms.txt', 'skill.md', 'sitemap.xml'];

    private const VERSION_FILES = ['llms.txt', 'llms-full.txt'];

    /** @var array<string, string> exported pages: URL path => HTML */
    private array $pages = [];

    /** @var array<string, string> other HTML files (redirects, 404): file => HTML */
    private array $documents = [];

    /** @var array<string, string> text files (agent Markdown, llms.txt, sitemap): file => body */
    private array $texts = [];

    /** @var list<string> */
    private array $leftOut = [];

    public function __construct(
        private readonly SiteClient $site,
        private readonly ListLocalizedPagesAction $localized,
    ) {}

    public function execute(ExportOptions $options): ExportResult
    {
        [$this->pages, $this->documents, $this->texts, $this->leftOut] = [[], [], [], []];
        $sets = [];

        foreach ($options->sets as $set) {
            if ($set->config->accessMode !== 'public') {
                $this->leftOut[] = "The {$set->key} set: it is private (access mode {$set->config->accessMode}), and a static host cannot check who is reading.";

                continue;
            }

            $sets[$set->key] = $this->exportSet($set);
        }

        return new ExportResult($this->write($options), $sets, [...$this->leftOut, ...$this->unavailable()]);
    }

    /** @return array{pages: int, markdown: int, private: int} */
    private function exportSet(DocSet $set): array
    {
        $counts = ['pages' => 0, 'markdown' => 0, 'private' => 0];

        foreach ($set->versions() as $version) {
            if ($version->visibility === Visibility::Private) {
                $this->leftOut[] = "Version {$version->id} of the {$set->key} set: it is private.";

                continue;
            }

            foreach ($set->locales() as $locale) {
                foreach ($this->localized->execute($set, $version->id, $locale->code) as $summary) {
                    $ref = $summary->ref->withLocale($locale->code);
                    $path = $this->site->pagePath($set, $ref);
                    $page = $this->site->get($path);

                    if (! $page->isOk()) {
                        $counts['private']++;

                        continue;
                    }

                    $this->pages[$path] = $page->body;
                    $counts['pages']++;

                    $markdownPath = $this->site->pagePath($set, $ref, true);
                    $markdown = $this->site->get($markdownPath, 'text/markdown');

                    if ($markdown->isOk()) {
                        $this->texts[ExportFiles::fileFor($markdownPath, false)] = $markdown->body;
                        $counts['markdown']++;
                    }
                }
            }

            if ($set->isVersioned()) {
                foreach (self::VERSION_FILES as $name) {
                    $this->surface($this->setPath($set, $version->id.'/'.$name));
                }
            }
        }

        foreach ($set->isVersioned() ? self::SET_FILES : [...self::SET_FILES, 'llms-full.txt'] as $name) {
            $this->surface($this->setPath($set, $name));
        }

        $this->entry($set);

        if ($counts['private'] > 0) {
            $this->leftOut[] = "{$counts['private']} private ".($counts['private'] === 1 ? 'page' : 'pages')." of the {$set->key} set.";
        }

        return $counts;
    }

    /** The set's own URL (its landing page, or a page that redirects to it) and its 404 page. */
    private function entry(DocSet $set): void
    {
        $root = $this->setPath($set, '');
        $response = $this->site->get($root);

        if ($response->isOk() && ! isset($this->pages[$root])) {
            $this->pages[$root] = $response->body;
        } elseif ($response->isRedirect()) {
            $this->documents[ExportFiles::fileFor($root, true)] = $this->redirectPage((string) parse_url((string) $response->location, PHP_URL_PATH));
        }

        // Inside the default version: on a versioned set, a URL without a version redirects there first.
        $missing = $this->site->get($this->setPath($set, ($set->isVersioned() ? $set->defaultVersion()->id.'/' : '').'__larecipe-export-404'));

        if ($missing->status === 404 && $missing->isHtml()) {
            $this->documents[trim($root, '/') === '' ? '404.html' : trim($root, '/').'/404.html'] = $missing->body;
        }
    }

    /** An agent surface (llms.txt, skill.md, sitemap.xml), when the site serves it as a real file. */
    private function surface(string $path): void
    {
        $response = $this->site->get($path, '*/*');

        if ($response->isOk() && ! $response->isHtml()) {
            $this->texts[ExportFiles::fileFor($path, false)] = $response->body;
        }
    }

    /** @return list<string> */
    private function write(ExportOptions $options): array
    {
        $files = new ExportFiles($options->out);
        $static = new StaticHtml(array_fill_keys(array_keys($this->pages), true), $options->basePath(), $this->site->origin());
        $references = [];

        foreach ($this->pages as $path => $html) {
            $references = [...$references, ...StaticHtml::references($html)];
            $files->write(ExportFiles::fileFor($path, true), $static->page($html));
        }

        foreach ($this->documents as $file => $html) {
            $references = [...$references, ...StaticHtml::references($html)];
            $files->write($file, $static->page($html));
        }

        foreach ($this->texts as $file => $body) {
            $files->write($file, $static->text($body));
        }

        foreach ($this->site->staticFolders() as $prefix => $folder) {
            $files->copyFolder($folder, $prefix);
        }

        // Images, styles, scripts and search indexes the pages point at, fetched as the site serves them.
        foreach (array_unique($references) as $path) {
            $file = ExportFiles::fileFor($path, false);

            if ($file === '' || isset($this->pages[$path]) || isset($this->pages[rtrim($path, '/')]) || $files->has($file)) {
                continue;
            }

            $response = $this->site->get($path, '*/*');

            if ($response->isOk() && ! $response->isHtml()) {
                $files->write($file, $response->body);
            } elseif (($public = $this->site->publicFile($path)) !== null) {
                // The web server serves public/ (a logo, the favicon), not the app.
                $files->write($file, $public);
            }
        }

        return $files->written();
    }

    private function setPath(DocSet $set, string $relative): string
    {
        return '/'.ltrim(trim($set->config->prefix, '/').'/'.ltrim($relative, '/'), '/');
    }

    private function redirectPage(string $target): string
    {
        $url = htmlspecialchars($target, ENT_QUOTES);

        return "<!doctype html>\n<meta charset=\"utf-8\">\n<title>Redirecting</title>\n<meta http-equiv=\"refresh\" content=\"0; url={$url}\">\n<link rel=\"canonical\" href=\"{$url}\">\n<a href=\"{$url}\">Continue to the docs</a>\n";
    }

    /** @return list<string> */
    private function unavailable(): array
    {
        return [
            'Ask AI: it answers through your server and your AI provider.',
            'The MCP server: agents connect to a running app.',
            'Page feedback: it reports to your app as a FeedbackSubmitted event.',
            'Server search (/_search?q=): the static site searches in the browser.',
            'Share links: the server checks them.',
        ];
    }
}
