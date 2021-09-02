<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Document;

class DocumentTest extends TestCase
{
    /** @test */
    public function it_can_be_returned_as_array()
    {
        $sut = Document::create();

        $this->assertIsArray($sut->toArray());
    }

    /** @test */
    public function it_fill_properties_from_static_create_method()
    {
        $sut = Document::create(['title' => 'Home page']);

        $this->assertEquals('Home page', $sut->title);
    }

    /** @test */
    public function it_check_if_has_content()
    {
        $sut = Document::create();
        $this->assertFalse($sut->hasContent());

        $sut = Document::create(['content' => 'test content here']);
        $this->assertTrue($sut->hasContent());
    }

    /** @test */
    public function it_parses_content_markdown()
    {
        $sut = Document::create();
        $sut->fill(['content' => '# hello']);
        $this->assertStringContainsString('<h1>hello</h1>', $sut->toArray()['content']);

        $sut->fill(['content' => '## hello']);
        $this->assertStringContainsString('<h2>hello</h2>', $sut->toArray()['content']);
    }
}
