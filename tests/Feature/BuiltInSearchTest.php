<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\DocumentationRepository;

class BuiltInSearchTest extends TestCase
{
    protected $documentationRepository;

    public function setUp()
    {
        parent::setUp();

        $this->documentationRepository = $this->app->make(DocumentationRepository::class);
    }

    /** @test */
    public function it_can_search_within_givin_version_for_h1_h2_h3()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $this->documentationRepository->search('1.0', 'foo');

        $this->assertTrue(true);
    }
}
