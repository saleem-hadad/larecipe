<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    /** @test */
    public function it_display_the_repository_button_only_if_the_url_is_set()
    {
        Config::set('larecipe.repository.url', '');

        $this->get('/docs/1.0')->assertDontSee('repository_button');

        Config::set('larecipe.repository.url', 'HollyMolly');

        $this->get('/docs/1.0')->assertSee('repository_button');
    }
}
