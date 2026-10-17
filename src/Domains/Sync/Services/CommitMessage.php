<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\PageChange;
use LaRecipe\Domains\Sync\DTOs\SyncReport;

/**
 * The commit message for `larecipe:sync --commit`: a subject that names what
 * changed, one line per page with the agent's reason, and a Co-Authored-By
 * trailer naming the agent and the model, so `git log` shows who wrote it.
 */
final class CommitMessage
{
    public const AUTHOR_NAME = 'LaRecipe docs agent';

    public const AUTHOR_EMAIL = 'noreply@larecipe.saleem.dev';

    public function build(SyncReport $report): string
    {
        $changes = $report->applied();
        $pages = array_values(array_filter($changes, fn (PageChange $change) => $change->isPage()));
        $names = array_map(fn (PageChange $change) => $change->subject, $pages);

        $subject = match (true) {
            $names === [] => 'Update the docs navigation and settings',
            count($names) <= 3 => 'Update docs: '.implode(', ', $names),
            default => 'Update '.count($names).' docs pages',
        };

        if (mb_strlen($subject) > 72) {
            $subject = 'Update '.count($names).' docs '.(count($names) === 1 ? 'page' : 'pages');
        }

        $body = [];

        foreach ($changes as $change) {
            $what = $change->movedTo !== null ? "{$change->subject} -> {$change->movedTo}" : $change->subject;
            $body[] = "- {$what}: ".($change->summary ?? $this->verb($change->tool));
        }

        return implode("\n", [
            $subject,
            '',
            ...$body,
            '',
            'Written by php artisan larecipe:sync to match the code. Review it before you push.',
            '',
            'Co-Authored-By: '.self::AUTHOR_NAME." ({$report->agent}) <".self::AUTHOR_EMAIL.'>',
        ]);
    }

    private function verb(string $tool): string
    {
        return match ($tool) {
            'move_page' => 'moved',
            'delete_page' => 'deleted',
            'update_navigation' => 'navigation updated',
            'update_manifest' => 'settings updated',
            default => 'updated',
        };
    }
}
