<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Documentation;

class DocumentationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        
        $this->documentation = $this->app->make(Documentation::class);
    }
    
    /** @test */
    public function it_checks_if_the_given_version_published()
    {
        Config::set('larecipe.versions.published', ['1.0', '1.1']);
        
        $this->assertTrue($this->documentation->isPublishedVersion('1.0'));
        $this->assertTrue($this->documentation->isPublishedVersion('1.1'));
        $this->assertFalse($this->documentation->isPublishedVersion('1.2'));
    }
    
    /** @test */
    public function it_can_parse_content()
    {
        $this->assertEquals('<p>hello</p>', $this->documentation->parse('hello'));
    }
}