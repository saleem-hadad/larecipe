<?php

namespace BinaryTorch\LaRecipe\Tests\Unit;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Tests\TestCase;
use BinaryTorch\LaRecipe\Models\Document;

class DocumentTest extends TestCase
{
    protected $document;

    public function setUp(): void
    {
        parent::setUp();

        $this->document = $this->app->make(Document::class);
    }

    /** @test */
    public function it_test()
    {
        $this->assertTrue(true);
    }
}
