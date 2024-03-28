<?php

namespace SaleemHadad\LaRecipe\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use SaleemHadad\LaRecipe\Tests\TestCase;
use SaleemHadad\LaRecipe\Interfaces\MarkdownParser;
use SaleemHadad\LaRecipe\Tests\Fixtures\HelloWorldMarkdownParser;

class InterchangeableMarkdownParserTest extends TestCase
{
    /** @test */
    public function a_parser_is_accepted_as_long_as_the_contract_matches()
    {
        Config::set('larecipe.source', 'tests/Fixture/docs');
        Config::set('larecipe.landing', 'custom-landing');

        // Provide a dummy parser
        $randomId = (string) Str::uuid();
        App::instance(MarkdownParser::class, new HelloWorldMarkdownParser($randomId));

        $this->get('/docs/en/1.0/custom-landing')
            ->assertSee("<h1>{$randomId}</h1>", false)
            ->assertDontSee('<h1>Custom Landing Page</h1>', false)
            ->assertStatus(200);
    }
}
