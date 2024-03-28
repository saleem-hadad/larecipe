<?php

namespace SaleemHadad\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use SaleemHadad\LaRecipe\Tests\TestCase;

class DocumentationControllerTest extends TestCase
{
    /** @test */
    public function a_guest_visit_root_path_will_be_redirected_to_default_landing_page()
    {
        $this->get('/docs')
            ->assertRedirect('/docs/en/1.0/overview');
    }

    /** @test */
    public function a_guest_can_access_any_documentation()
    {
        Config::set('larecipe.source', 'tests/Fixture/docs');
        Config::set('larecipe.landing', 'custom-landing');
        
        $this->get('/docs/en/1.0/custom-landing')
            ->assertSee('<h1>Custom Landing Page</h1>', false)
            ->assertStatus(200);
    }

    /** @test */
    public function show_not_exist_version_throw_not_found_exception()
    {
        Config::set('larecipe.source', 'tests/Fixture/docs');
        Config::set('larecipe.landing', 'custom-landing');
        Config::set('larecipe.versions.published', ['1.0']);

        $this->get('/docs/2.0/foo')
            ->assertStatus(404);
    }

    /** @test */
    public function a_guest_may_not_get_contents_of_not_exists_documentation()
    {
        Config::set('larecipe.source', 'tests/views/docs');
        Config::set('larecipe.landing', 'foo');

        $this->get('/docs/1.0/bar')
            ->assertStatus(404);
    }

    /** @test */
    public function only_authorized_users_can_access_viewLarecipe_gate_is_defined()
    {
        Config::set('larecipe.source', 'tests/Fixture/docs');
        Config::set('larecipe.landing', 'custom-landing');
        Gate::define('viewLarecipe', function($user, $documentation) {
            return false;
        });

        $this->get('/docs/en/1.0/custom-landing')->assertStatus(403);
    }
}
