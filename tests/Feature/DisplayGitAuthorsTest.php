<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use BinaryTorch\LaRecipe\Tests\TestCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Contracts\GitService as GitServiceContract;
use BinaryTorch\LaRecipe\Tests\Fixtures\DummyGitService;

class DisplayGitAuthorsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');
    }

    /** @test */
    public function if_git_config_is_not_enabled_page_will_be_displayed_without_errors()
    {
        Config::set('larecipe.git.enabled', false);
        
        App::instance(GitServiceContract::class, new DummyGitService(true, []));

        $this->get('/docs/1.0')
            ->assertOk()
            ->assertViewHas('authors', function($authors) {
                return is_null($authors);
            });
    }

    /** @test */
    public function if_git_is_not_installed_page_will_be_displayed_without_errors()
    {
        App::instance(GitServiceContract::class, new DummyGitService(false, []));

        $this->get('/docs/1.0')
            ->assertOk()
            ->assertViewHas('authors', function($authors) {
                return is_null($authors);
            });
    }

    /** @test */
    public function if_git_is_installed_but_no_authors_are_found_page_will_be_displayed_without_errors()
    {
        # code...
    }
}
