<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Version;
use BinaryTorch\LaRecipe\Exceptions\FillUnknownPropertyException;

class VersionTest extends TestCase
{
    /** @test */
    public function it_has_title()
    {
        $sut = Version::create(['title' => 'v1']);

        $this->assertEquals('v1', $sut->title);
    }
}
