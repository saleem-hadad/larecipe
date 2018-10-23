<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use BinaryTorch\LaRecipe\Tests\TestCase;

class BuiltInSearchTest extends TestCase
{
    /** @test */
    public function a_consol_command_used_to_index_docs_in_cash()
    {
        $this->artisan('larecipe:index')
            ->expectsOutput('Reading published versions..');
    }
}
