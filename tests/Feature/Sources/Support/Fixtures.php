<?php

namespace LaRecipe\Tests\Feature\Sources\Support;

use FilesystemIterator;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Helpers for the Sources tests: writable copies of fixture doc sets in temp
 * directories (fixtures themselves are never written to), and short ways to
 * write pages through the real Actions.
 */
final class Fixtures
{
    /** Copy tests/Fixtures/{name} into a fresh temp directory and return its path. */
    public static function copy(string $name): string
    {
        $target = self::temporaryDirectory(str_replace('/', '-', $name));
        self::copyDirectory(TestCase::fixture($name), $target);

        return $target;
    }

    public static function temporaryDirectory(string $prefix = 'larecipe'): string
    {
        $directory = sys_get_temp_dir().'/larecipe-track-f/'.$prefix.'-'.bin2hex(random_bytes(6));
        mkdir($directory, 0755, true);

        return $directory;
    }

    public static function copyDirectory(string $from, string $to): void
    {
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($items as $item) {
            $target = $to.'/'.substr($item->getPathname(), strlen($from) + 1);
            $item->isDir() ? (is_dir($target) || mkdir($target, 0755, true)) : copy($item->getPathname(), $target);
        }
    }

    public static function agent(string $name = 'claude-code'): Provenance
    {
        return new Provenance(Actor::Agent, $name);
    }

    public static function human(string $name = 'Saleem'): Provenance
    {
        return new Provenance(Actor::Human, $name);
    }

    /** @param array<string, mixed> $frontmatter */
    public static function write(string $set, string $path, string $body, array $frontmatter = [], Channel $channel = Channel::PullRequest, ?Provenance $author = null, string $version = 'current', string $locale = 'en'): WriteResult
    {
        $frontmatter += ['description' => 'A page written by a test.'];

        return app(WritePageAction::class)->execute(PageDraft::make(
            new PageRef($set, $version, $locale, $path),
            $frontmatter,
            $body,
            $author ?? self::agent(),
            $channel,
            "Write {$path}",
        ));
    }
}
