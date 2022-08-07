<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use BinaryTorch\LaRecipe\Tests\TestCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Contracts\GitService as GitServiceContract;
use BinaryTorch\LaRecipe\Tests\Fixtures\DummyGitService;

class DisplayGitAuthorsTest extends TestCase
{
    /** @test */
    public function if_git_config_is_not_enabled_page_will_be_displayed_without_errors()
    {
        Config::set('larecipe.git.enabled', false);
        
        App::instance(GitServiceContract::class, new DummyGitService(true, []));

        $this->get('/docs/1.0')
            ->assertViewHas('authors', null);
    }

    /** @test */
    public function if_git_is_not_installed_page_will_be_displayed_without_errors()
    {
        # code...
    }

    /** @test */
    public function if_git_is_installed_but_no_authors_are_found_page_will_be_displayed_without_errors()
    {
        # code...
    }
}
