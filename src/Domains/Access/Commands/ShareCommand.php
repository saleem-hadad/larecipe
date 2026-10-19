<?php

namespace LaRecipe\Domains\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\UrlGenerator;
use InvalidArgumentException;
use LaRecipe\Domains\Access\Actions\IssueShareLinkAction;
use LaRecipe\Domains\Access\DTOs\ShareLink;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;

/**
 * php artisan larecipe:share docs/3.x/billing/roadmap --expires=7d
 *
 * Prints a temporary link that lets anyone read one page, private or not, as
 * HTML, Markdown or JSON. The set and version may be left out when the app has
 * one set or the page is in the default version.
 */
final class ShareCommand extends Command
{
    protected $signature = 'larecipe:share
        {page : The page, as set/version/path (for example docs/3.x/billing/roadmap)}
        {--expires=7d : How long the link works: 30m, 12h, 7d, 2w}';

    protected $description = 'Issue a temporary signed link that lets anyone read one private page';

    public function handle(SetRegistry $sets, IssueShareLinkAction $issue, UrlGenerator $url): int
    {
        try {
            [$set, $ref] = $this->locate($sets, (string) $this->argument('page'));
            $link = $issue->execute($ref, (string) $this->option('expires'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $address = $url->route("larecipe.{$set->key}.page", ['path' => $this->pathFor($set, $ref)]);
        $address .= (str_contains($address, '?') ? '&' : '?').ShareLink::QUERY.'='.$link->token;

        $this->line($address);
        $this->newLine();
        $this->components->info(sprintf(
            'Anyone with this link can read %s until %s, even if the page is private. Rotating APP_KEY revokes every share link.',
            $ref->path,
            $link->expiresAt->format('Y-m-d H:i T'),
        ));

        return self::SUCCESS;
    }

    /**
     * Reads "set/locale/version/path", where the set, the locale and the version
     * are each optional and recognized by name.
     *
     * @return array{0: DocSet, 1: PageRef}
     */
    private function locate(SetRegistry $sets, string $page): array
    {
        $segments = array_values(array_filter(explode('/', trim($page, '/')), fn ($segment) => $segment !== ''));
        $set = $segments !== [] && $sets->has($segments[0]) ? $sets->get(array_shift($segments)) : $sets->default();
        $locale = $set->defaultLocale()->code;
        $version = $set->defaultVersion()->id;

        if ($segments !== [] && $set->isMultilingual() && $set->locale($segments[0]) !== null) {
            $locale = array_shift($segments);
        }

        if ($segments !== [] && $set->isVersioned() && $set->version($segments[0]) !== null) {
            $version = array_shift($segments);
        }

        return [$set, new PageRef($set->key, $version, $locale, PageRef::normalizePath(implode('/', $segments)))];
    }

    /** The page's path inside its set's route group: [locale/][version/][path]. */
    private function pathFor(DocSet $set, PageRef $ref): string
    {
        $segments = [];

        if ($set->isMultilingual() && $ref->locale !== $set->defaultLocale()->code) {
            $segments[] = $ref->locale;
        }

        if ($set->isVersioned()) {
            $segments[] = $ref->version;
        }

        if (! $ref->isIndex()) {
            $segments[] = $ref->path;
        }

        return implode('/', $segments);
    }
}
