<?php

namespace BinaryTorch\LaRecipe\Tests;

use \LaRecipe;

class RouteTest extends TestCase
{
    /** @test */
    public function docs_index_route_will_redirect_to_docs_show_with_default_version()
    {
        $this->call('GET', '/docs')->assertRedirect(
            config('larecipe.docs.route') . '/' . config('larecipe.versions.default')
        );
    }
    
    /** @test */
    public function docs_show_route_has_required_version_and_optional_page_parameters()
    {
        $this->call('GET', '/docs/1.0')->assertStatus(200);
        $this->call('GET', '/docs/1.0/installation')->assertStatus(200);
    }
}