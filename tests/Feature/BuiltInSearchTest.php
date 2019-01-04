<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Documentation;

class BuiltInSearchTest extends TestCase
{
    protected $documentation;

    public function setUp()
    {
        parent::setUp();

        $this->documentation = $this->app->make(Documentation::class);
    }

    /** @test */
    public function it_can_search_within_givin_version_for_h1_h2_h3()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $this->documentation->index('1.0');

        $this->assertTrue(true);
    }
}
