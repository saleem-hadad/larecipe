<?php

namespace BinaryTorch\LaRecipe\Tests;

class RouteTest extends TestCase
{
    /** @test */
    public function docs_index_route_will_redirect_to_docs_show_with_default_version()
    {
        $defaultVersionPath = config('larecipe.docs.route') . '/' . config('larecipe.versions.default');

        $this->call('GET', '/docs')->assertRedirect($defaultVersionPath);
    }
}
