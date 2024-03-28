<?php

namespace SaleemHadad\LaRecipe\Tests\Unit;

use SaleemHadad\LaRecipe\Tests\TestCase;
use SaleemHadad\LaRecipe\Models\Document;

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
    public function it_parses_content_markdown()
    {
        $sut = Document::create();
        $sut->fill(['content' => '# hello']);
        $this->assertStringContainsString('<h1>hello</h1>', $sut->toArray()['content']);

        $sut->fill(['content' => '## hello']);
        $this->assertStringContainsString('<h2><a id="hello" href="#hello" class="heading-permalink" aria-hidden="true" title="Permalink">#</a>hello</h2>', $sut->toArray()['content']);
    }
}
