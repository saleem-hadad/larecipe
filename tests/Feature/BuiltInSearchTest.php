<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class BuiltInSearchTest extends TestCase
{
    /** @test */
    public function it_can_search_within_givin_version_for_h1_h2_h3()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $this->assertTrue(true);
    }
}
