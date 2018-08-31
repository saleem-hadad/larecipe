<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function setup()
    {
        parent::setup();

        $this->app->setBasePath(__DIR__ . '/../..');

        $this->app['router']->get('login', function () {
            return 'login';
        })->name('login');

        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');
    }

    /** @test */
    public function it_display_the_repository_button_only_if_the_url_is_set()
    {
        Config::set('larecipe.repository.url', '');

        $this->get('/docs/1.0')->assertDontSee('repository_button');

        Config::set('larecipe.repository.url', 'HollyMolly');

        $this->get('/docs/1.0')->assertSee('repository_button');
    }

    /** @test */
    public function it_allows_guest_access_if_auth_not_enabled()
    {
        config()->set('larecipe.settings.auth', false);
        $this->get('/docs/1.0')->assertStatus(200);
    }

    /** @test */
    public function it_prevent_access_docs_for_guests_if_auth_is_enabled()
    {
        config()->set('larecipe.settings.auth', true);
        $this->get('/docs/1.0')->assertRedirect('login');
    }
}
