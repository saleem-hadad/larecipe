<?php
namespace BinaryTorch\LaRecipe\Tests\Fixtures;

use BinaryTorch\LaRecipe\Contracts\MarkdownParser;

class HelloWorldMarkdownParser implements MarkdownParser
{
    private $suffix;

    public function __construct(string $suffix)
    {
        $this->suffix = $suffix;
    }

    public function parse($source)
    {
        return <<<HTML
        <h1>{$this->suffix}</h1>

        This is a test client, don't use in any other application!
        HTML;
    }
}
