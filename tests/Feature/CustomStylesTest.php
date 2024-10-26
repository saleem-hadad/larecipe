<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use BinaryTorch\LaRecipe\LaRecipe;
use BinaryTorch\LaRecipe\Tests\TestCase;

class CustomStylesTest extends TestCase
{
    /** @test */
    public function a_style_not_found_returns_404()
    {
        $this->get('/docs/styles/not-found')
            ->assertNotFound();
    }

    /** @test */
    public function a_style_is_returned()
    {
        LaRecipe::style('custom-style', __DIR__ . '/../theme/resources/css/custom.css');

        $this->get('/docs/styles/custom-style')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('Custom CSS');
    }
}
