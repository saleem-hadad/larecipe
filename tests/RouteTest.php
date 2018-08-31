<?php

namespace BinaryTorch\LaRecipe\Tests;

class RouteTest extends TestCase
{
    /** @test */
    public function a_user_will_be_redirected_to_default_version_and_landing_page_when_visit_the_root_route()
    {
        $this->call('GET', '/docs')->assertRedirect(
            config('larecipe.docs.route') . '/' . config('larecipe.versions.default') . '/' . config('larecipe.docs.landing')
        );
    }

    /** @test */
    public function a_user_may_not_visit_doc_page_if_not_exists()
    {
        $this->call('GET', '/docs/1.0/bar')
            ->assertSee('Not Found')
            ->assertStatus(404);
    }
}
