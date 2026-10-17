<?php

namespace LaRecipe\Tests\Contracts;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;

/**
 * The contract every content source driver must pass (ARCHITECTURE.md 5.1).
 *
 * Both built-in drivers run it, and it is published so a third-party driver
 * (LaRecipe::extendSources) can prove it behaves the same way: what is
 * written reads back with the same content hash, pages live per version and
 * locale, moves and deletes do what they say, navigation falls back to the
 * default locale, the manifest keeps unknown keys, assets round trip, and the
 * fingerprint moves when content does.
 *
 * Use it in a PHPUnit test class that boots LaRecipe (Testbench) and return a
 * new, empty source for the set "contract" from emptySource().
 */
trait ContentSourceContract
{
    abstract protected function emptySource(): WritableContentSource;

    private function contractAuthor(): Provenance
    {
        return new Provenance(Actor::Human, 'contract');
    }

    private function contractPage(string $path, string $body, array $frontmatter = [], string $version = '3.x', string $locale = 'en'): Page
    {
        return Page::make(new PageRef('contract', $version, $locale, $path), new Frontmatter($frontmatter), $body);
    }

    private function versionedSource(): WritableContentSource
    {
        $source = $this->emptySource();
        $source->writeManifest(Manifest::fromArray([
            'name' => 'Contract',
            'versions' => [['id' => '3.x', 'default' => true], ['id' => '2.x']],
            'locales' => [['code' => 'en', 'default' => true], ['code' => 'ar']],
        ]), $this->contractAuthor());

        return $source;
    }

    /** @return list<string> */
    private function paths(WritableContentSource $source, string $version, string $locale): array
    {
        $paths = [];

        foreach ($source->pages($version, $locale) as $ref) {
            $paths[] = $ref->path;
        }

        return $paths;
    }

    public function test_a_new_source_is_empty(): void
    {
        $source = $this->emptySource();

        $this->assertNull($source->manifest());
        $this->assertSame([], $this->paths($source, Version::IMPLICIT, 'en'));
        $this->assertNull($source->page(new PageRef('contract', Version::IMPLICIT, 'en', 'index')));
        $this->assertNull($source->navigation(Version::IMPLICIT, 'en'));
    }

    public function test_a_written_page_reads_back_with_the_same_content_hash(): void
    {
        $source = $this->versionedSource();
        $page = $this->contractPage('billing/exports', "# Exports\n\nCSV, with `code` and a | table |.\n", [
            'title' => 'Exports',
            'description' => 'Download invoices.',
            'order' => 3,
            'ratio' => 1.5,
            'hidden' => false,
            'sources' => ['app/Billing/**'],
            'badge' => ['label' => 'New', 'tone' => 'info'],
            'x-custom' => 'kept',
        ]);

        $revision = $source->writePage($page, $this->contractAuthor());
        $read = $source->page($page->ref);

        $this->assertNotNull($read);
        $this->assertSame($page->contentHash, $read->contentHash);
        $this->assertSame($page->body, $read->body);
        $this->assertSame('Exports', $read->title);
        $this->assertSame('kept', $read->frontmatter->get('x-custom'));
        $this->assertSame($page->contentHash, $revision->contentHash);
        $this->assertSame('page', $revision->subjectType);
    }

    public function test_pages_are_listed_per_version_and_locale(): void
    {
        $source = $this->versionedSource();
        $source->writePage($this->contractPage('index', "# Home\n"), $this->contractAuthor());
        $source->writePage($this->contractPage('guides/install', "# Install\n"), $this->contractAuthor());
        $source->writePage($this->contractPage('index', "# Old home\n", version: '2.x'), $this->contractAuthor());
        $source->writePage($this->contractPage('index', "# الرئيسية\n", locale: 'ar'), $this->contractAuthor());

        $this->assertSame(['guides/install', 'index'], $this->paths($source, '3.x', 'en'));
        $this->assertSame(['index'], $this->paths($source, '2.x', 'en'));
        $this->assertSame(['index'], $this->paths($source, '3.x', 'ar'));
        $this->assertSame("# الرئيسية\n", $source->page(new PageRef('contract', '3.x', 'ar', 'index'))?->body);
    }

    public function test_rewriting_a_page_replaces_its_content(): void
    {
        $source = $this->versionedSource();
        $source->writePage($this->contractPage('faq', "# FAQ\n"), $this->contractAuthor());
        $source->writePage($this->contractPage('faq', "# FAQ\n\nUpdated.\n"), $this->contractAuthor());

        $this->assertSame("# FAQ\n\nUpdated.\n", $source->page(new PageRef('contract', '3.x', 'en', 'faq'))?->body);
        $this->assertSame(['faq'], $this->paths($source, '3.x', 'en'));
    }

    public function test_pages_move_to_another_path_version_or_locale(): void
    {
        $source = $this->versionedSource();
        $page = $this->contractPage('faq', "# FAQ\n", ['title' => 'FAQ']);
        $source->writePage($page, $this->contractAuthor());

        $to = new PageRef('contract', '2.x', 'ar', 'help/faq');
        $revision = $source->movePage($page->ref, $to, $this->contractAuthor());

        $this->assertNull($source->page($page->ref));
        $this->assertSame($page->contentHash, $source->page($to)?->contentHash);
        $this->assertSame('move', $revision->operation);
        $this->assertSame(['help/faq'], $this->paths($source, '2.x', 'ar'));
    }

    public function test_a_move_onto_a_taken_path_or_of_a_missing_page_is_refused(): void
    {
        $source = $this->versionedSource();
        $source->writePage($this->contractPage('a', "# A\n"), $this->contractAuthor());
        $source->writePage($this->contractPage('b', "# B\n"), $this->contractAuthor());

        try {
            $source->movePage(new PageRef('contract', '3.x', 'en', 'a'), new PageRef('contract', '3.x', 'en', 'b'), $this->contractAuthor());
            $this->fail('A move onto a taken path must be refused.');
        } catch (InvalidContentChange) {
            $this->assertSame("# B\n", $source->page(new PageRef('contract', '3.x', 'en', 'b'))?->body);
        }

        $this->expectException(InvalidContentChange::class);
        $source->movePage(new PageRef('contract', '3.x', 'en', 'missing'), new PageRef('contract', '3.x', 'en', 'c'), $this->contractAuthor());
    }

    public function test_pages_are_deleted(): void
    {
        $source = $this->versionedSource();
        $page = $this->contractPage('old', "# Old\n");
        $source->writePage($page, $this->contractAuthor());

        $revision = $source->deletePage($page->ref, $this->contractAuthor());

        $this->assertNull($source->page($page->ref));
        $this->assertSame([], $this->paths($source, '3.x', 'en'));
        $this->assertSame('delete', $revision->operation);
    }

    public function test_navigation_is_stored_per_version_and_shared_with_other_locales(): void
    {
        $source = $this->versionedSource();
        $tree = NavigationTree::fromJson(['groups' => [['group' => 'Start', 'icon' => 'rocket', 'pages' => ['index', ['page' => 'faq', 'label' => 'Questions']]]]]);
        $arabic = NavigationTree::fromJson(['groups' => ['index']]);

        $source->writeNavigation('3.x', 'en', $tree, $this->contractAuthor());

        $this->assertSame($tree->toJson(), $source->navigation('3.x', 'en')?->toJson());
        $this->assertSame($tree->toJson(), $source->navigation('3.x', 'ar')?->toJson());
        $this->assertNull($source->navigation('2.x', 'en'));

        $source->writeNavigation('3.x', 'ar', $arabic, $this->contractAuthor());

        $this->assertSame($arabic->toJson(), $source->navigation('3.x', 'ar')?->toJson());
        $this->assertSame($tree->toJson(), $source->navigation('3.x', 'en')?->toJson());
    }

    public function test_removing_navigation_brings_back_zero_config(): void
    {
        $source = $this->versionedSource();
        $source->writeNavigation('3.x', 'en', NavigationTree::fromJson(['groups' => ['index']]), $this->contractAuthor());
        $source->removeNavigation('3.x', 'en', $this->contractAuthor());

        $this->assertNull($source->navigation('3.x', 'en'));
    }

    public function test_the_manifest_round_trips_with_keys_larecipe_does_not_know(): void
    {
        $source = $this->emptySource();
        $raw = ['$schema' => 'https://larecipe.saleem.dev/schemas/3.x/larecipe.json', 'name' => 'Acme', 'theme' => ['accent' => '#6d5efc'], 'x-team' => ['owner' => 'docs']];

        $source->writeManifest(Manifest::fromArray($raw), $this->contractAuthor());

        $this->assertSame($raw, $source->manifest()?->toArray());
        $this->assertSame('Acme', $source->manifest()?->name);
    }

    public function test_assets_are_stored_listed_and_served(): void
    {
        $source = $this->versionedSource();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $source->writeAsset('3.x', '_assets/pixel.png', $png);
        $source->writeAsset(Version::IMPLICIT, 'theme.css', ':root { --lr-accent: red; }');

        $this->assertSame(['_assets/pixel.png'], iterator_to_array($source->assets('3.x'), false));
        $this->assertSame($png, $source->asset('3.x', 'pixel.png')?->contents());
        $this->assertSame('image/png', $source->asset('3.x', '_assets/pixel.png')?->mime);
        $this->assertSame(':root { --lr-accent: red; }', $source->asset(Version::IMPLICIT, 'theme.css')?->contents());

        $source->deleteAsset('3.x', '_assets/pixel.png');

        $this->assertNull($source->asset('3.x', 'pixel.png'));
    }

    public function test_the_fingerprint_moves_when_content_changes(): void
    {
        $source = $this->versionedSource();
        $before = $source->fingerprint();

        $source->writePage($this->contractPage('new', "# New\n"), $this->contractAuthor());

        $this->assertNotSame($before, $source->fingerprint());
    }

    public function test_writes_never_leave_the_set(): void
    {
        $source = $this->versionedSource();

        $this->expectException(UnsafePath::class);
        $source->writePage($this->contractPage('../../escape', "# Escape\n"), $this->contractAuthor(), WriteMode::Publish);
    }
}
