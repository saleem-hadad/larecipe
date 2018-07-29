<?php

namespace BinaryTorch\LaRecipe\Tests;

use \LaRecipe;

class ExampleTest extends TestCase
{
    /** @test */
    public function it_parse_mark_down_correctly()
    {
        $this->assertEquals('<p>hello</p>', LaRecipe::parse('hello'));
    }
}