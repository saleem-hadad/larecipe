<?php

namespace SaleemHadad\LaRecipe\Tests\Unit;

use SaleemHadad\LaRecipe\Models\Language;
use SaleemHadad\LaRecipe\Tests\TestCase;
use SaleemHadad\LaRecipe\Models\Version;

class LanguageTest extends TestCase
{
    /** @test */
    public function it_has_title()
    {
        $sut = Language::create(['title' => 'v1']);

        $this->assertEquals('v1', $sut->title);
    }

    /** @test */
    public function it_has_empty_versions_by_default()
    {
        $sut = Language::create();

        $this->assertEmpty($sut->getVersions());
    }

    /** @test */
    public function it_can_add_version()
    {
        $sut = Language::create();

        $sut->addVersion(Version::create());

        $this->assertCount(1, $sut->getVersions());
    }
}
