<?php

use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Services\GitLogParser;

$record = fn (string $sha, string $author, string $email, string $message, array $files) => "\x1e{$sha}\x1f{$author}\x1f{$email}\x1f2026-10-12T09:30:00+00:00\x1f{$message}\x1d\n\n".implode("\n", $files)."\n";

it('takes the newest change of each file', function () use ($record) {
    $log = $record('bbbbbbbbbbbbbbbb', 'Saleem', 's@example.com', "Fix typo\n", ['installation.md'])
        .$record('aaaaaaaaaaaaaaaa', 'Someone', 'o@example.com', "Initial\n", ['installation.md', 'index.md']);

    $files = (new GitLogParser)->parse($log);

    expect($files['installation.md']->name)->toBe('Saleem')
        ->and($files['installation.md']->ref)->toBe('bbbbbbbbbbbb')
        ->and($files['index.md']->name)->toBe('Someone')
        ->and($files['index.md']->at->format('Y-m-d'))->toBe('2026-10-12');
});

it('credits the agent named in a Co-Authored-By trailer, approved by the committer', function () use ($record) {
    $message = "Document exports (#412)\n\nBody.\n\nCo-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>\n";
    $files = (new GitLogParser)->parse($record('cccccccccccccccc', 'Saleem', 's@example.com', $message, ['billing/exports.md']), 'https://github.com/acme/acme');

    expect($files['billing/exports.md']->actor)->toBe(Actor::Agent)
        ->and($files['billing/exports.md']->name)->toBe('Claude Opus 5.5')
        ->and($files['billing/exports.md']->approvedBy)->toBe('Saleem')
        ->and($files['billing/exports.md']->ref)->toBe('https://github.com/acme/acme/pull/412');
});

it('ignores human co-authors and reads merge commits and bot authors', function () use ($record) {
    $log = $record('dddddddddddddddd', 'Saleem', 's@example.com', "Merge pull request #7 from acme/docs\n\nCo-Authored-By: Jane <jane@example.com>\n", ['a.md'])
        .$record('eeeeeeeeeeeeeeee', 'copilot-swe-agent[bot]', 'bot@github.com', "Update docs\n", ['b.md']);

    $files = (new GitLogParser)->parse($log);

    expect($files['a.md']->actor)->toBe(Actor::Human)
        ->and($files['a.md']->ref)->toBe('#7')
        ->and($files['b.md']->actor)->toBe(Actor::Agent)
        ->and($files['b.md']->name)->toBe('Copilot');
});
