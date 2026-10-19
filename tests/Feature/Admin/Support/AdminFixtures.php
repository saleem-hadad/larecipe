<?php

namespace LaRecipe\Tests\Feature\Admin\Support;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

/**
 * The people and content the admin tests work with: a manager the
 * manageLarecipe gate lets in, a signed-in stranger it does not, and short
 * ways to put pages, agent drafts and docs-migration conflicts in the "kb"
 * database set through the real write Actions.
 */
final class AdminFixtures
{
    public const API = '/kb/_admin/api';

    public static function defineGate(): void
    {
        Gate::define('manageLarecipe', fn ($user, string $set) => $user->email === 'ada@example.com');
    }

    public static function manager(): GenericUser
    {
        return new GenericUser(['id' => 1, 'name' => 'Ada Reviewer', 'email' => 'ada@example.com', 'remember_token' => null]);
    }

    public static function stranger(): GenericUser
    {
        return new GenericUser(['id' => 2, 'name' => 'Sam Reader', 'email' => 'sam@example.com', 'remember_token' => null]);
    }

    /** @param array<string, mixed> $frontmatter */
    public static function page(string $path, string $body, array $frontmatter = []): Page
    {
        $result = Fixtures::write('kb', $path, $body, $frontmatter + ['title' => self::title($path)], Channel::PullRequest, new Provenance(Actor::Migration, 'claude-code', '2026_10_01_000000_docs_seed'));

        return Page::make(new PageRef('kb', 'current', 'en', $path), new Frontmatter($result->revision->payload['frontmatter']), $result->revision->payload['body']);
    }

    /** An agent's change made outside a pull request: a draft under review: required. */
    public static function agentDraft(string $path, string $body, array $frontmatter = []): Revision
    {
        return Fixtures::write('kb', $path, $body, $frontmatter + ['title' => self::title($path)], Channel::RemoteAgent, Fixtures::agent('claude-code'))->revision;
    }

    /**
     * A docs migration written against $path as it was, applied after a
     * person edited the page in production: the migration's version is kept
     * as a conflict draft next to production's.
     */
    public static function conflict(string $path): Revision
    {
        $original = self::page($path, "# Escalation\n\nPage the on-call engineer.\n");

        Fixtures::write('kb', $path, "# Escalation\n\nPage the on-call engineer, then open an incident.\n", ['title' => 'Escalation'], Channel::AdminUi, Fixtures::human('Production Editor'));

        return app(WritePageAction::class)->execute(new PageDraft(
            page: Page::make(new PageRef('kb', 'current', 'en', $path), new Frontmatter(['title' => 'Escalation', 'description' => 'A page written by a test.']), "# Escalation\n\nPage the on-call engineer through PagerDuty.\n"),
            author: new Provenance(Actor::Migration, 'claude-code', 'https://github.com/acme/acme/pull/482'),
            channel: Channel::Migration,
            summary: 'Document PagerDuty escalation',
            basedOn: $original->contentHash,
            migration: '2026_10_17_141502_docs_pagerduty',
        ))->revision;
    }

    private static function title(string $path): string
    {
        return ucfirst(str_replace('-', ' ', basename($path)));
    }
}
