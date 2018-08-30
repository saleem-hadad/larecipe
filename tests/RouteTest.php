<?php

namespace BinaryTorch\LaRecipe\Tests;

class RouteTest extends TestCase
{
    /** @test */
    public function docs_index_route_will_redirect_to_docs_show_with_default_version()
    {
        $defaultRoute   = config('larecipe.docs.route');
        $defaultLanding = config('larecipe.docs.landing');
        $defaultVersion = config('larecipe.versions.default');
        $redirectPath   = "$defaultRoute/$defaultVersion/$defaultLanding";

        $this->call('GET', '/docs')->assertRedirect($redirectPath);
    }
}
