<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use BinaryTorch\LaRecipe\LaRecipe;
use BinaryTorch\LaRecipe\Tests\TestCase;

class CustomScriptsTest extends TestCase
{
    /** @test */
    public function a_script_not_found_returns_404()
    {
        $this->get('/docs/scripts/not-found')
            ->assertNotFound();
    }

    /** @test */
    public function a_script_is_returned()
    {
        LaRecipe::script('custom-script', __DIR__ . '/../theme/resources/js/custom.js');

        $this->get('/docs/scripts/custom-script')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript')
            ->assertSee('Custom JS');
    }
}
