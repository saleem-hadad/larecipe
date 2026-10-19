<?php

/*
 * The scanner some validation rules read Markdown source with. Code is
 * literal, so an example in inline code or a code block is never a finding.
 */

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\Services\MarkdownScanner;

it('skips tags written in inline code', function () {
    $body = "Write `<Video placeholder=\"Soon\" />` to keep a slot, or ``<Video placeholder=\"Later\" />`` with longer ticks.\n\n<Video placeholder=\"Real\" />";

    expect(collect((new MarkdownScanner)->tags($body, 'Video'))->pluck('props.placeholder')->all())->toBe(['Real']);
});

it('does not report a video placeholder written as an example in inline code', function () {
    $page = Page::make(
        new PageRef('docs', 'current', 'en', 'example'),
        new Frontmatter(['title' => 'Example', 'description' => 'An example.']),
        "# Example\n\nWrite `<Video placeholder=\"Soon\" />` to keep a slot.\n",
    );

    expect(collect(app(ValidatePageAction::class)->execute($page)->issues)->pluck('rule'))->not->toContain('media.placeholder');
});

it('skips code blocks, including a line that only looks like a closing fence', function () {
    $body = implode("\n", [
        '```markdown',
        '```php',
        '<Video placeholder="Inside" />',
        '```',
        '',
        '1. A step:',
        '',
        '    ```markdown',
        '    <Video placeholder="In a list" />',
        '    <<< @/app/Example.php#region',
        '    ```',
        '',
        '<Video placeholder="Real" />',
        '<<< @/app/Real.php',
    ]);

    $scanner = new MarkdownScanner;

    expect(collect($scanner->tags($body, 'Video'))->pluck('props.placeholder')->all())->toBe(['Real'])
        ->and(collect($scanner->snippets($body))->pluck('path')->all())->toBe(['app/Real.php']);
});
