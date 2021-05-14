<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\BusinessLogic\RequestPathParser;
use BinaryTorch\LaRecipe\Contracts\RequestPathParser as RequestPathParserContract;

class RequestPathParserTest extends TestCase
{
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->app->make(RequestPathParserContract::class);
    }

    /** @test */
    public function it_can_parse_request_path_string_and_returns_RequestPathParser()
    {
        $returned = $this->sut->parse("someString");

        $this->assertEquals(RequestPathParser::class, get_class($returned));
    }

    /** @test */
    public function it_can_parse_language_from_path_if_enabled()
    {
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.versions.enabled', false);

        $this->sut->parse("en/v1/overview");

        $parts = $this->sut->getPathParts();
        $this->assertEquals("en", $parts['language']);
        $this->assertEquals("v1/overview.md", $parts['relativePath']);
    }

    /** @test */
    public function it_can_parse_version_from_path_if_enabled()
    {
        Config::set('larecipe.versions.enabled', true);

        $this->sut->parse("v1/overview");

        $parts = $this->sut->getPathParts();
        $this->assertEquals("v1", $parts['version']);
        $this->assertEquals("overview.md", $parts['relativePath']);
    }

    /** @test */
    public function it_can_parse_language_and_version_from_path_if_enabled()
    {
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.versions.enabled', true);

        $this->sut->parse("en/v1/section/one");

        $parts = $this->sut->getPathParts();
        $this->assertEquals("en", $parts['language']);
        $this->assertEquals("v1", $parts['version']);
        $this->assertEquals("section/one.md", $parts['relativePath']);
    }
}
