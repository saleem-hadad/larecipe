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
}