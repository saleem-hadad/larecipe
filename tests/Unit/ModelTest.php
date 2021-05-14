<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Models\Model;
use BinaryTorch\LaRecipe\Tests\TestCase;

class ModelTest extends TestCase
{
    /** @test */
    public function can_get_property_using_magic_method_get()
    {
        $sut = new class extends Model {
            protected $fillable = ['title'];
            public function toArray() { }
        };

        $sut->fill(['title' => 'Home page']);

        $this->assertEquals('Home page', $sut->title);
    }

    /** @test */
    public function it_fill_attributes_if_key_is_inside_fillable()
    {
        $sut = new class extends Model {
            protected $fillable = ['title'];
            public function toArray() { }
        };

        $sut->fill(['title' => 'Home page', 'foo' => 'bar']);

        $this->assertEquals('Home page', $sut->title);
        $this->assertEquals(null, $sut->foo);
    }
}
