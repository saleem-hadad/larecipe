<?php

namespace BinaryTorch\LaRecipe\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Contracts\MarkdownParser;
use BinaryTorch\LaRecipe\Tests\Fixtures\HelloWorldMarkdownParser;

class InterchangeableMarkdownParserTest extends TestCase
{
    /** @test */
    public function a_parser_is_accepted_as_long_as_the_contract_matches()
    {
        // set the docs path and landing
        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');

        // set auth to false
        Config::set('larecipe.settings.auth', false);

        // Provide a dummy parser
        $randomId = (string) Str::uuid();
        App::instance(MarkdownParser::class, new HelloWorldMarkdownParser($randomId));

        // guest can view foo page
        $this->get('/docs/1.0')
            ->assertViewHasAll([
                'title',
                'index',
                'content',
                'currentVersion',
                'versions',
                'currentSection',
                'canonical'
            ])
            ->assertSee("<h1>{$randomId}</h1>", false)
            ->assertDontSee('<h1>Foo</h1>', false)
            ->assertStatus(200);
    }
}
