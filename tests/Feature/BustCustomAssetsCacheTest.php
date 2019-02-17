<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class BustCustomAssetsCacheTest extends TestCase
{
    /** @test */
    public function custom_css_and_js_display_random_string_on_query_param()
    {
        // set the docs path and landing
        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');

        // set auth to false
        Config::set('larecipe.settings.auth', false);

        $customCssFile = 'veryUniqueCssCustomFile.css';
        $customJsFile = 'veryUniqueJsCustomFile.js';

        Config::set('larecipe.ui.additional_css', [ "/js/{$customCssFile}" ]);
        Config::set('larecipe.ui.additional_js', [ "/js/{$customJsFile}" ]);

        // guest can view foo page
        $this->get('/docs/1.0')
            ->assertStatus(200)
            ->assertSee("/js/{$customCssFile}?id=")
            ->assertSee("/js/{$customJsFile}?id=");
    }
}
