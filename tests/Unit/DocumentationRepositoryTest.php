<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\DocumentationRepository;

class DocumentationRepositoryTest extends TestCase
{
    protected $documentationRepository;

    public function setUp()
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
}
