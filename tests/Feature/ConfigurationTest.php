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

    /** @test */
    public function back_to_top_button_is_visible_only_if_enabled()
    {
        Config::set('larecipe.ui.back_to_top', false);
        $this->get('/docs/1.0')->assertDontSee('<larecipe-back-to-top></larecipe-back-to-top>');

        Config::set('larecipe.ui.back_to_top', true);
        $this->get('/docs/1.0')->assertSee('<larecipe-back-to-top></larecipe-back-to-top>');
    }

    /** @test */
    public function ga_script_is_visible_only_if_ga_id_is_set()
    {
        Config::set('larecipe.settings.ga_id', '');
        $this->get('/docs/1.0')
            ->assertDontSee('googletagmanager');

        Config::set('larecipe.settings.ga_id', 'ga_code_id');
        $this->get('/docs/1.0')
            ->assertSee('googletagmanager')
                ->assertSee('ga_code_id');
    }

    /** @test */
    public function app_name_is_visible_only_if_enabled()
    {
        Config::set('larecipe.ui.show_app_name', false);
        Config::set('app.name', 'hoolla');
        $this->get('/docs/1.0')
            ->assertDontSee('<span>hoolla</span>');

        Config::set('larecipe.ui.show_app_name', true);
        Config::set('app.name', 'hoolla');
        $this->get('/docs/1.0')
            ->assertSee('<span>hoolla</span>');
    }
}
