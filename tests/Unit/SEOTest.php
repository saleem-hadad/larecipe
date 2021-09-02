<?php


namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Models\SEO;
use BinaryTorch\LaRecipe\Tests\TestCase;

class SEOTest extends TestCase
{
    /** @test */
    public function it_can_be_returned_as_array()
    {
        $sut = SEO::create(['ogTitle' => 'Page title']);

        $this->assertIsArray($sut->toArray());

        $this->assertEquals([
            'author' => null,
            'description' => null,
            'keywords' => null,
            'ogTitle' => 'Page title',
            'ogType' => null,
            'ogUrl' => null,
            'ogImage' => null,
            'ogDescription' => null
        ], $sut->toArray());
    }
}
