<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    /** @test */
    public function repository_button_is_visible_only_if_the_repository_url_is_given()
    {
        Config::set('larecipe.repository.url', '');

        $this->get('/docs/1.0')->assertDontSee('repository_button');

        Config::set('larecipe.repository.url', 'HollyMolly');

        $this->get('/docs/1.0')->assertSee('repository_button');
    }

    /** @test */
    public function a_guest_can_access_any_documentation_if_auth_is_not_enabled()
    {
        // set the docs path and landing
        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');

        // set auth to false
        Config::set('larecipe.settings.auth', false);
        
        // guest can view foo page
        $this->get('/docs/1.0')->assertStatus(200);
    }

    /** @test */
    public function only_auth_user_can_visit_docs_if_auth_option_is_enabled()
    {
        config()->set('larecipe.settings.auth', true);
        $this->get('/docs/1.0')->assertRedirect('login');
    }
}
