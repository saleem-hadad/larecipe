<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Document;

class DocumentTest extends TestCase
{
    /** @test */
    public function it_can_be_returned_as_array()
    {
        $sut = Document::create();

        $this->assertIsArray($sut->toArray());
    }

    /** @test */
    public function it_fill_properties_from_static_create_method()
    {
        $sut = Document::create(['title' => 'Home page']);

        $this->assertEquals('Home page', $sut->title);
    }

    /** @test */
    public function it_check_if_has_content()
    {
        $sut = Document::create();
        $this->assertFalse($sut->hasContent());

        $sut = Document::create(['content' => 'test content here']);
        $this->assertTrue($sut->hasContent());
    }

    /** @test */
    public function it_parses_content_markdown()
    {
        $sut = Document::create();
        $sut->fill(['content' => '# hello']);
        $this->assertStringContainsString('<h1>hello</h1>', $sut->toArray()['content']);

        $sut->fill(['content' => '## hello']);
        $this->assertStringContainsString('<h2>hello</h2>', $sut->toArray()['content']);
    }

//    /** @test */
//    public function it_has_index_for_a_given_version()
//    {
//        Config::set('larecipe.settings.path', 'tests/views/docs');
//
//        $documentation = $this->documentationRepository->get('1.0', 'foo');
//
//        $this->assertStringContainsString('Get Started', $documentation->index);
//    }
//
//    /** @test */
//    public function it_has_canonical()
//    {
//        Config::set('larecipe.settings.path', 'tests/views/docs');
//
//        $documentation = $this->documentationRepository->get('1.0', 'foo');
//        $this->assertEquals('http://localhost/docs/1.0/foo', $documentation->canonical);
//
//        $documentation = $this->documentationRepository->get('1.0', 'bar');
//        $this->assertEmpty($documentation->canonical);
//    }
//
//    /** @test */
//    public function it_has_default_version_url()
//    {
//        Config::set('larecipe.settings.path', 'tests/views/docs');
//
//        $documentation = $this->documentationRepository->get('1.0', 'foo');
//
//        $this->assertEquals('http://localhost/docs/1.0', $documentation->defaultVersionUrl);
//    }
//
//    /** @test */
//    public function it_can_check_if_a_version_published_or_not()
//    {
//        Config::set('larecipe.versions.published', ['1.0']);
//
//        $this->assertTrue($this->documentationRepository->isPublishedVersion('1.0'));
//        $this->assertFalse($this->documentationRepository->isNotPublishedVersion('1.0'));
//
//        $this->assertFalse($this->documentationRepository->isPublishedVersion('2.0'));
//        $this->assertTrue($this->documentationRepository->isNotPublishedVersion('2.0'));
//    }
//
//    /** @test */
//    public function it_has_status_code_and_by_default_200()
//    {
//        $this->assertEquals(200, $this->documentationRepository->statusCode);
//
//        // if a user requested not exists file => 404
//        $documentation = $this->documentationRepository->get('1.0', 'bar');
//        $this->assertEquals(404, $this->documentationRepository->statusCode);
//    }
//
//    /** @test */
//    public function it_has_all_published_versions()
//    {
//        Config::set('larecipe.versions.published', ['1.0', '2.0', '2.1']);
//
//        // re-initializing the object, this happened because the published versions list gets
//        // stored once the object has been initialized, so changing the config will not be
//        // reflected in the existing object, so we have to get a new fresh instance..
//        $this->documentationRepository = $this->app->make(DocumentationRepository::class);
//        $this->assertEquals(['1.0', '2.0', '2.1'], $this->documentationRepository->publishedVersions);
//    }

//    /** @test */
//    public function it_can_parse_content()
//    {
//        $this->assertEquals("<p>hello</p>\n", $this->documentation->parse('hello'));
//
//        $this->assertEquals("<h1>Hello World!</h1>\n", $this->documentation->parse('# Hello World!'));
//    }
//
//    /** @test */
//    public function it_replace_placeholders_with_the_given_version_and_route()
//    {
//        $this->assertEquals(
//            'the current version is 1.1 and the route is /docs',
//            $this->documentation->replaceLinks('1.1', 'the current version is {{version}} and the route is /{{route}}')
//        );
//    }
//
//    /** @test */
//    public function it_caches_the_requested_documentation_and_index_for_a_given_period()
//    {
//        $cache = $this->app->make(\BinaryTorch\LaRecipe\Cache::class);
//        $app_version = explode('.', app()->version());
//
//        // set the docs path and landing
//        Config::set('larecipe.settings.path', 'tests/views/docs');
//        Config::set('larecipe.settings.landing', 'foo');
//        Config::set('larecipe.versions.default', '1.0');
//        Config::set('larecipe.cache.enabled', true);
//        Config::set('larecipe.cache.period', 300);
//
//        $this->documentation->getIndex('1.0');
//        $this->assertNotEmpty(cache('larecipe.settings.1.0.index'));
//
//        $this->documentation->get('1.0', 'foo');
//        $this->assertNotEmpty(cache('larecipe.settings.1.0.foo'));
//
//        $this->documentation->get('1.0', 'bar');
//        $this->assertNull(cache('larecipe.settings.1.0.bar'));
//
//        if (((int) $app_version[0] == 5 && (int) $app_version[1] >= 8) || $app_version[0] > 5) {
//            $this->assertEquals($cache->checkTtlNeedsChanged(300), 300 * 60);
//        } else {
//            $this->assertEquals($cache->checkTtlNeedsChanged(300), 300);
//        }
//    }
//
//    /** @test */
//    public function it_can_compile_blade_content()
//    {
//        $bladeContent = "{{ csrf_field() }}";
/*        $this->assertEquals('<?php echo e(csrf_field()); ?>', $this->documentation->compileBlade($bladeContent));*/
//    }
//
//    /** @test */
//    public function it_can_render_blade_content()
//    {
//        $content = "{{ count(['foo', 'bar']) }}";
//        $this->assertEquals(2, $this->documentation->renderBlade($content));
//    }
//
//    /** @test */
//    public function it_does_not_render_blade_content_within_code_blocks()
//    {
//        $content = "<pre><code>{{ count(['foo', 'bar']) }}</code></pre>";
//        $this->assertEquals($content, $this->documentation->renderBlade($content));
//    }
}
