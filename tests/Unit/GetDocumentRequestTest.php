<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\BusinessLogic\GetDocumentRequest;
use BinaryTorch\LaRecipe\Contracts\GetDocumentRequest as GetDocumentRequestContract;

class GetDocumentRequestTest extends TestCase
{
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->app->make(GetDocumentRequestContract::class);
    }

    /** @test */
    public function it_can_parse_request_path_string_and_returns_GetDocumentRequest()
    {
        $returned = $this->sut->parse("someString");

        $this->assertEquals(GetDocumentRequest::class, get_class($returned));
    }

    /** @test */
    public function it_can_parse_language_from_path_if_enabled()
    {
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.versions.enabled', false);

        $this->sut->parse("en/v1/overview");

        $this->assertEquals("en", $this->sut->getLanguage());
        $this->assertEquals("en/v1/overview", $this->sut->getPath());
        $this->assertNull($this->sut->getVersion());
    }

    /** @test */
    public function it_can_parse_version_from_path_if_enabled()
    {
        Config::set('larecipe.languages.enabled', false);
        Config::set('larecipe.versions.enabled', true);

        $this->sut->parse("v1/overview");

        $this->assertEquals("v1", $this->sut->getVersion());
        $this->assertEquals("v1/overview", $this->sut->getPath());
        $this->assertNull($this->sut->getLanguage());
    }

    /** @test */
    public function it_can_parse_language_and_version_from_path_if_enabled()
    {
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.versions.enabled', true);

        $this->sut->parse("en/v1/section/one");

        $this->assertEquals("en", $this->sut->getLanguage());
        $this->assertEquals("v1", $this->sut->getVersion());
        $this->assertEquals("en/v1/section/one", $this->sut->getPath());
    }
}
