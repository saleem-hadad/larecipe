<?php


namespace BianryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Exceptions\FillUnknownPropertyException;
use BinaryTorch\LaRecipe\Models\Model;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ModelTest extends TestCase
{
    /** @test */
    public function can_get_property_using_magic_method_get()
    {
        $sut = new class extends Model {
            protected $title;
            public function toArray() { }
        };

        $sut->fill(['title' => 'Home page']);

        $this->assertEquals('Home page', $sut->title);
    }

    /** @test */
    public function it_may_not_fill_unknown_property()
    {
        $sut = new class extends Model {
            public function toArray() { }
        };

        $this->expectException(FillUnknownPropertyException::class);

        $sut->fill(['foo' => 'Home page']);
    }

    /** @test */
    public function it_fill_properties_from_fill_method()
    {
        $sut = new class extends Model {
            protected $title;
            public function toArray() { }
        };

        $sut->fill(['title' => 'Home page']);

        $this->assertEquals('Home page', $sut->title);
    }
}
