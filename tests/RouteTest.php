<?php

namespace BinaryTorch\LaRecipe\Tests;

use \LaRecipe;

class RouteTest extends TestCase
{
    /** @test */
    public function has_routes()
    {
        $urls = [
            route('docs.index'),
        ];
        
        foreach ($urls as $url) {
            $this->call('GET', $url)->assertStatus(200);
        }
    }
}