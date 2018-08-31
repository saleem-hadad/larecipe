<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Documentation;

class DocumentationTest extends TestCase
{
    protected $documentation;

    public function setUp()
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

        $this->assertEquals('<h1>hello</h1>', $this->documentation->parse('#hello'));
    }

    /** @test */
    public function it_replace_version_placeholder_with_the_given_version()
    {
        $this->assertEquals(
            'the current version is 1.1',
            $this->documentation->replaceLinks('1.1', 'the current version is {{version}}')
        );
    }
    
    /** @test */
    public function it_caches_the_requested_documentation_and_index_for_a_given_period()
    {
        // set the docs path and landing
        Config::set('larecipe.docs.path', 'tests/views/docs');
        Config::set('larecipe.docs.landing', 'foo');
        Config::set('larecipe.versions.default', '1.0');

        $this->documentation->getIndex('1.0');
        $this->assertNotEmpty(cache('larecipe.docs.1.0.index'));

        $this->documentation->get('1.0', 'foo');
        $this->assertNotEmpty(cache('larecipe.docs.1.0.foo'));

        $this->documentation->get('1.0', 'bar');
        $this->assertNull(cache('larecipe.docs.1.0.bar'));
    }
}
