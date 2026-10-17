<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Support\Contracts\Clock;

/**
 * Remembers which pushes the pre-push check already held, per agent session,
 * so it blocks at most once for the same commit and the same list of pages.
 *
 * The second attempt always passes: an agent (or a person) that decided no
 * docs change is needed must never be stuck. State is a small JSON file per
 * session; files older than a week are cleaned up as new ones are written.
 */
final class LoopGuard
{
    private const TTL = 7 * 24 * 3600;

    public function __construct(
        private readonly string $directory,
        private readonly Clock $clock,
    ) {}

    public function alreadyHeld(string $session, string $key): bool
    {
        return isset($this->state($session)[$key]);
    }

    public function remember(string $session, string $key): void
    {
        if ($this->directory === '' || (! is_dir($this->directory) && ! @mkdir($this->directory, 0755, true) && ! is_dir($this->directory))) {
            return;
        }

        $state = $this->state($session);
        $state[$key] = $this->clock->now()->getTimestamp();

        @file_put_contents($this->file($session), json_encode($state, JSON_PRETTY_PRINT));
        $this->prune();
    }

    /** @param list<string> $pages */
    public static function key(?string $head, array $pages): string
    {
        sort($pages);

        return hash('sha256', ($head ?? 'no-commit')."\n".implode("\n", $pages));
    }

    /** @return array<string, int> */
    private function state(string $session): array
    {
        $file = $this->file($session);

        if (! is_file($file)) {
            return [];
        }

        $state = json_decode((string) file_get_contents($file), true);

        return is_array($state) ? $state : [];
    }

    private function file(string $session): string
    {
        return rtrim($this->directory, '/').'/'.$session.'.json';
    }

    private function prune(): void
    {
        $cutoff = $this->clock->now()->getTimestamp() - self::TTL;

        foreach (glob(rtrim($this->directory, '/').'/*.json') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
