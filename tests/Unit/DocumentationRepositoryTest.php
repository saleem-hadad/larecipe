<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\DocumentationRepository;

class DocumentationRepositoryTest extends TestCase
{
    protected $documentationRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->documentationRepository = $this->app->make(DocumentationRepository::class);
    }

    /** @test */
    public function it_has_title_if_a_doc_contains_h1()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $documentation = $this->documentationRepository->get('1.0', 'foo');

        $this->assertEquals('Foo', $documentation->title);
    }

    /** @test */
    public function it_has_index_for_a_given_version()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $documentation = $this->documentationRepository->get('1.0', 'foo');

        $this->assertStringContainsString('Get Started', $documentation->index);
    }

    /** @test */
    public function it_has_content()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $documentation = $this->documentationRepository->get('1.0', 'foo');

        $this->assertStringContainsString('Section 1', $documentation->content);
    }

    /** @test */
    public function it_has_canonical()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $documentation = $this->documentationRepository->get('1.0', 'foo');
        $this->assertEquals('http://localhost/docs/1.0/foo', $documentation->canonical);

        $documentation = $this->documentationRepository->get('1.0', 'bar');
        $this->assertEmpty($documentation->canonical);
    }

    /** @test */
    public function it_has_default_version_url()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $documentation = $this->documentationRepository->get('1.0', 'foo');

        $this->assertEquals('http://localhost/docs/1.0', $documentation->defaultVersionUrl);
    }

    /** @test */
    public function it_can_check_if_a_version_published_or_not()
    {
        Config::set('larecipe.versions.published', ['1.0']);

        $this->assertTrue($this->documentationRepository->isPublishedVersion('1.0'));
        $this->assertFalse($this->documentationRepository->isNotPublishedVersion('1.0'));

        $this->assertFalse($this->documentationRepository->isPublishedVersion('2.0'));
        $this->assertTrue($this->documentationRepository->isNotPublishedVersion('2.0'));
    }

    /** @test */
    public function it_has_status_code_and_by_default_200()
    {
        $this->assertEquals(200, $this->documentationRepository->statusCode);

        // if a user requested not exists file => 404
        $documentation = $this->documentationRepository->get('1.0', 'bar');
        $this->assertEquals(404, $this->documentationRepository->statusCode);
    }

    /** @test */
    public function it_has_all_published_versions()
    {
        Config::set('larecipe.versions.published', ['1.0', '2.0', '2.1']);

        // re-initializing the object, this happened because the published versions list gets
        // stored once the object has been initialized, so changing the config will not be
        // reflected in the existing object, so we have to get a new fresh instance..
        $this->documentationRepository = $this->app->make(DocumentationRepository::class);
        $this->assertEquals(['1.0', '2.0', '2.1'], $this->documentationRepository->publishedVersions);
    }
}
