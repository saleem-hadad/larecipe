<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Documentation;

class DocumentationTest extends TestCase
{
    /** @test */
    public function it_checks_if_the_given_version_published()
    {
        $documentation = new Documentation();
        
        $this->assertTrue($documentation->isPublishedVersion('1.0'));
        $this->assertFalse($documentation->isPublishedVersion('1.1'));
    }
    
    /** @test */
    public function it_return_the_correct_docs_page_and_parse_it()
    {
        $documentation = new Documentation();
        
        $this->assertEquals('<p>hello</p>', $documentation->parse('hello'));
        $this->assertEquals('<p>hello</p>', $documentation->get('1.0', 'overview'));
    }
}