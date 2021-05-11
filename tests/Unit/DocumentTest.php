<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Document;
use BinaryTorch\LaRecipe\Exceptions\FillUnknownPropertyException;

class DocumentTest extends TestCase
{
    /** @test */
    public function it_can_be_created_from_static_create_method_without_attributes()
    {
        $document = Document::create();

        $this->assertInstanceOf(Document::class, $document);
    }

    /** @test */
    public function it_can_be_returned_as_array()
    {
        $document = Document::create();

        $this->assertArrayHasKey('title', $document->toArray());
        $this->assertArrayHasKey('content', $document->toArray());
    }

    /** @test */
    public function it_fill_properties_from_static_create_method()
    {
        $document = Document::create(['title' => 'Home page']);

        $this->assertEquals('Home page', $document->title);
    }

    /** @test */
    public function it_fill_properties_from_fill_method()
    {
        $document = Document::create();

        $document->fill(['title' => 'Home page']);

        $this->assertEquals('Home page', $document->title);
    }

    /** @test */
    public function it_may_not_fill_unknown_property()
    {
        $document = Document::create();

        $this->expectException(FillUnknownPropertyException::class);

        $document->fill(['foo' => 'Home page']);
    }

    /** @test */
    public function can_get_property_using_magic_method_get()
    {
        $document = Document::create(['content' => 'Body']);

        $this->assertEquals(null, $document->title);
        $this->assertEquals('Body', $document->content);
    }
}
