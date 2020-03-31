<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    /** @test */
    public function favicon_is_visible_only_if_ui_fav_is_set()
    {
        Config::set('larecipe.ui.fav', '');
        $this->get('/docs/1.0')
            ->assertDontSee('rel="apple-touch-icon"', false)
            ->assertDontSee('rel="shortcut icon"', false);

        Config::set('larecipe.ui.fav', 'http://localhost/favicon.ico');
        $this->get('/docs/1.0')
            ->assertSee('rel="apple-touch-icon" href="http://localhost/favicon.ico"', false)
            ->assertSee('rel="shortcut icon" type="image/png" href="http://localhost/favicon.ico"', false);
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
    public function seo_support()
    {
        Config::set('larecipe.seo.author', 'Binary Torch Author');
        Config::set('larecipe.seo.description', 'seo description is here');
        Config::set('larecipe.seo.keywords', 'key1, key2, key3');
        Config::set('larecipe.seo.og.title', 'open graph title');
        Config::set('larecipe.seo.og.type', 'open graph type');
        Config::set('larecipe.seo.og.url', 'open graph url');
        Config::set('larecipe.seo.og.image', 'open graph image');
        Config::set('larecipe.seo.og.description', 'open graph description');

        $this->get('/docs/1.0')
            ->assertSee('Binary Torch Author')
            ->assertSee('seo description is here')
            ->assertSee('key1, key2, key3')
            ->assertSee('open graph title')
            ->assertSee('open graph type')
            ->assertSee('open graph url')
            ->assertSee('open graph image')
            ->assertSee('open graph description');
    }

    /** @test */
    public function search_button_will_be_visible_when_search_is_enabled()
    {
        Config::set('larecipe.search.enabled', false);
        $this->get('/docs/1.0')
            ->assertDontSee('search-button');

        Config::set('larecipe.search.enabled', true);
        $this->get('/docs/1.0')
            ->assertSee('search-button');
    }

    /** @test */
    public function algolia_search_will_be_visible_only_if_selected_and_enabled()
    {
        Config::set('larecipe.search.default', '');
        Config::set('larecipe.search.enabled', false);
        $this->get('/docs/1.0')
            ->assertDontSee('algolia-search-box');

        Config::set('larecipe.search.default', 'algolia');
        Config::set('larecipe.search.enabled', true);
        $this->get('/docs/1.0')
            ->assertSee('algolia-search-box');
    }

    /** @test */
    public function disqus_forum_will_be_visible_only_if_selected_and_enabled()
    {
        Config::set('larecipe.forum.default', '');
        Config::set('larecipe.forum.enabled', false);
        $this->get('/docs/1.0')
            ->assertDontSee('disqus_thread');

        Config::set('larecipe.forum.default', 'disqus');
        Config::set('larecipe.forum.enabled', true);
        Config::set('larecipe.forum.services.disqus.site_name', 'larecipe');
        $this->get('/docs/1.0')
            ->assertSee('disqus_thread');
    }
}
