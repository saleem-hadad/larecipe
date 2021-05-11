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

        $this->assertEquals([
            'seo' => null,
            'path' => null,
            'title' => null,
            'content' => null,
            'canonical' => null
        ], $sut->toArray());
    }

    /** @test */
    public function it_fill_properties_from_static_create_method()
    {
        $document = Document::create(['title' => 'Home page']);

        $this->assertEquals('Home page', $document->title);
    }
}
