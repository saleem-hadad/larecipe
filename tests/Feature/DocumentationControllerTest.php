<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;

class DocumentationControllerTest extends TestCase
{
    /** @test */
    public function a_guest_visit_root_path_will_be_redirected_to_default_landing_page()
    {
        $this->get('/docs')
            ->assertRedirect('/docs/en/1.0/overview');
    }
}
