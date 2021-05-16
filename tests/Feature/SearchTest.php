<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class SearchTest extends TestCase
{
    /** @test */
    public function can_search_within_givin_version_for_h1_h2_h3()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        // activate built-in search..
        Config::set('larecipe.search.enabled', true);
        Config::set('larecipe.search.default', 'internal');

        $this->get('/docs/search-index/1.0')
            ->assertStatus(200)
            ->assertJsonStructure([
                [
                    'path',
                    'title',
                    'headings'
                ]
            ]);
    }
}
