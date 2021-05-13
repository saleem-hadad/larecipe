<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Models\DocumentRequest;
use BinaryTorch\LaRecipe\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class DocumentRequestTest extends TestCase
{
    /** @test */
    public function it_can_return_the_full_path_of_requested_document_including_version_if_enabled()
    {
        Config::set('larecipe.versions.enabled', true);
        Config::set('larecipe.languages.enabled', false);
        Config::set('larecipe.docs.path', 'docs');

        $sut = new DocumentRequest('en', 'v1', 'test');

        $this->assertStringContainsString("docs/v1/test.md", $sut->getDocumentPath());
        $this->assertStringNotContainsString("/en/", $sut->getDocumentPath());
    }

    /** @test */
    public function it_can_return_the_full_path_of_requested_document_including_language_if_enabled()
    {
        Config::set('larecipe.versions.enabled', false);
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.docs.path', 'docs');

        $sut = new DocumentRequest('en', 'v1', 'test');

        $this->assertStringContainsString("docs/en/test.md", $sut->getDocumentPath());
        $this->assertStringNotContainsString("/v1/", $sut->getDocumentPath());
    }

    /** @test */
    public function it_can_return_the_full_path_of_requested_document_including_language_and_version__if_enabled()
    {
        Config::set('larecipe.versions.enabled', true);
        Config::set('larecipe.languages.enabled', true);
        Config::set('larecipe.docs.path', 'docs');

        $sut = new DocumentRequest('en', 'v1', 'test');

        $this->assertStringContainsString("docs/en/v1/test.md", $sut->getDocumentPath());
    }
}
