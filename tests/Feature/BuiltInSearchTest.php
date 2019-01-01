<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Documentation;

class BuiltInSearchTest extends TestCase
{
    /** @test */
    public function a_consol_command_used_to_index_docs_in_cash()
    {
        Config::set('larecipe.docs.path', 'tests/views/docs');

        $this->artisan('larecipe:index')
            ->expectsOutput('Reading published versions..')
            ->expectsOutput('Reading index.md for v1.0');

        Documentation::search('Two');
    }
}
