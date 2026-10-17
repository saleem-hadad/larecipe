<?php

namespace Workbench\App\Console;

use Illuminate\Console\Command;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use Workbench\App\Providers\AdminWorkbenchServiceProvider;

/**
 * Resets the "runbooks" database set the admin UI is developed and
 * browser-tested against: fresh tables in the workbench's SQLite file, three
 * published pages, an agent's draft waiting at the pass, and a docs migration
 * that arrived after someone edited the same page in production (a conflict).
 */
final class SeedRunbooksCommand extends Command
{
    protected $signature = 'workbench:seed-runbooks';

    protected $description = 'Reset the runbooks database set with pages, a draft and a docs-migration conflict';

    public function handle(SourceManager $sources): int
    {
        $database = AdminWorkbenchServiceProvider::databaseFile();
        @mkdir(dirname($database), 0755, true);
        file_put_contents($database, '');

        $this->callSilently('migrate', ['--database' => AdminWorkbenchServiceProvider::CONNECTION, '--force' => true]);
        $this->callSilently('cache:clear');
        $sources->forget();

        $pull = new Provenance(Actor::Migration, 'claude-code', '2026_10_01_090000_docs_runbooks');

        app(UpdateManifestAction::class)->execute(new ManifestDraft(AdminWorkbenchServiceProvider::SET, [
            'name' => 'Runbooks',
            'description' => 'How the platform team runs production.',
            'theme' => ['accent' => '#6d5efc'],
            'agents' => ['llmsTxt' => ['description' => 'Runbooks for the Acme platform team.']],
        ], $pull, Channel::Migration, 'Start the runbooks'));

        $this->write('index', ['title' => 'Runbooks', 'description' => 'Start here when something breaks.'], "# Runbooks\n\nEvery runbook says what to check first and who to call.\n\n- [Escalation](on-call/escalation.md)\n- [Rolling back a deploy](deploys/rollback.md)\n", $pull, Channel::Migration);
        $escalation = $this->write('on-call/escalation', ['title' => 'Escalation', 'description' => 'Who to call, and when.', 'sources' => ['app/Incidents/**']], "# Escalation\n\nPage the on-call engineer.\n", $pull, Channel::Migration);
        $this->write('deploys/rollback', ['title' => 'Rolling back a deploy', 'description' => 'Undo a bad release in minutes.'], "# Rolling back a deploy\n\nRun the rollback from the deploy dashboard.\n\n```bash\nphp artisan down\n```\n", $pull, Channel::Migration);

        // An agent outside a pull request: a draft under review: required.
        $this->write('deploys/rollback', ['title' => 'Rolling back a deploy', 'description' => 'Undo a bad release in minutes.'], "# Rolling back a deploy\n\nRun the rollback from the deploy dashboard, then tell the incident channel.\n\n<Callout type=\"warning\">\nRoll back the database migrations last.\n</Callout>\n\n```bash\nphp artisan down\n```\n", new Provenance(Actor::Agent, 'claude-code'), Channel::RemoteAgent, 'Mention the incident channel');

        // Someone edits production, then a docs migration written against the old version arrives.
        $this->write('on-call/escalation', ['title' => 'Escalation', 'description' => 'Who to call, and when.', 'sources' => ['app/Incidents/**']], "# Escalation\n\nPage the on-call engineer, then open an incident.\n", new Provenance(Actor::Human, 'Production Editor'), Channel::AdminUi);
        app(WritePageAction::class)->execute(new PageDraft(
            page: Page::make(new PageRef(AdminWorkbenchServiceProvider::SET, 'current', 'en', 'on-call/escalation'), new Frontmatter(['title' => 'Escalation', 'description' => 'Who to call, and when.', 'sources' => ['app/Incidents/**']]), "# Escalation\n\nPage the on-call engineer through PagerDuty.\n"),
            author: new Provenance(Actor::Migration, 'claude-code', 'https://github.com/acme/platform/pull/482'),
            channel: Channel::Migration,
            summary: 'Document PagerDuty escalation',
            basedOn: $escalation->contentHash,
            migration: '2026_10_17_141502_docs_pagerduty',
        ));

        $this->components->info('The runbooks set is ready at /'.AdminWorkbenchServiceProvider::SET.'/_admin (sign in at /workbench/sign-in/manager).');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $frontmatter */
    private function write(string $path, array $frontmatter, string $body, Provenance $author, Channel $channel, ?string $summary = null): Page
    {
        $page = Page::make(new PageRef(AdminWorkbenchServiceProvider::SET, 'current', 'en', $path), new Frontmatter($frontmatter), $body);
        app(WritePageAction::class)->execute(new PageDraft($page, $author, $channel, $summary ?? "Write {$path}"));

        return $page;
    }
}
