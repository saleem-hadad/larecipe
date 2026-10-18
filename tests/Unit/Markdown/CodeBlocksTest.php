<?php

/*
 * Code blocks: the figure contract, Phiki highlighting with the larecipe-pass
 * theme, info-string meta, and the per-block highlight cache.
 */

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use LaRecipe\Domains\Markdown\DTOs\CodeFence;
use LaRecipe\Domains\Markdown\Services\CodeHighlighter;
use LaRecipe\Domains\Markdown\Support\PassTheme;
use LaRecipe\Support\CacheFormat;
use LaRecipe\Tests\Unit\Markdown\Render;

it('renders a fenced block as a figure with a header and one span per line', function () {
    $html = Render::html("```php title=\"app/Models/User.php\"\n\$user = User::find(1);\nreturn \$user;\n```")->html;

    expect($html)->toStartWith('<figure class="lr-code" data-language="php" data-title="app/Models/User.php"><figcaption class="lr-code-header"><span class="lr-code-title">app/Models/User.php</span></figcaption><pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">')
        ->and($html)->toContain("</span>\n<span class=\"lr-line\" data-line=\"2\">")
        ->and($html)->toEndWith("</code></pre></figure>\n")
        ->and($html)->not->toContain('copy');
});

it('highlights with the larecipe-pass colors', function () {
    $html = Render::html("```php\n// note\nfunction refund(): string { return 'done'; }\n```")->html;

    expect($html)->toContain('<span style="color:'.PassTheme::COMMENT.'">// note</span>')
        ->and($html)->toContain('<span style="color:'.PassTheme::KEYWORD.'">function</span>')
        ->and($html)->toContain('<span style="color:'.PassTheme::FUNCTION.'">refund</span>')
        ->and($html)->toContain('<span style="color:'.PassTheme::STRING.'">\'done\'</span>');
});

it('labels a block by its language when it has no title', function () {
    expect(Render::html("```bash\nls\n```")->html)->toContain('<span class="lr-code-title">bash</span>')
        ->and(Render::html("```\nplain\n```")->html)->toContain('data-language="text"><figcaption class="lr-code-header"><span class="lr-code-title">text</span>');
});

it('marks highlighted lines and line numbers', function () {
    $html = Render::html("```js {2,4-5} showLineNumbers\na\nb\nc\nd\ne\n```")->html;

    expect($html)->toContain('<pre class="lr-pre" tabindex="0" data-line-numbers>')
        ->and($html)->toContain('<span class="lr-line" data-line="1">')
        ->and($html)->toContain('<span class="lr-line" data-line="2" data-highlighted>')
        ->and($html)->toContain('<span class="lr-line" data-line="3">')
        ->and($html)->toContain('<span class="lr-line" data-line="4" data-highlighted>')
        ->and($html)->toContain('<span class="lr-line" data-line="5" data-highlighted>');
});

it('marks added and removed lines with the diff meta, highlighting the code as its own language', function () {
    $html = Render::html("```php diff\n  \$a = 1;\n- \$b = 2;\n+ \$b = 3;\n```")->html;

    expect($html)->toContain('<pre class="lr-pre" tabindex="0" data-diff>')
        ->and($html)->toMatch('#<span class="lr-line" data-line="1">\$a#')
        ->and($html)->toMatch('#<span class="lr-line" data-line="2" data-diff="remove">\$b#')
        ->and($html)->toMatch('#<span class="lr-line" data-line="3" data-diff="add">\$b#');
});

it('keeps the markers of a diff block', function () {
    $html = Render::html("```diff\n--- a\n+++ b\n-old\n+new\n same\n```")->html;

    expect($html)->toContain('<span class="lr-line" data-line="1">')
        ->and($html)->toContain('<span class="lr-line" data-line="3" data-diff="remove">')
        ->and($html)->toContain('<span class="lr-line" data-line="4" data-diff="add">')
        ->and(strip_tags($html))->toContain('+new');
});

it('falls back to escaped text for unknown languages', function () {
    $html = Render::html("```klingon\n<b>Qapla'</b>\n```")->html;

    expect($html)->toContain('data-language="klingon"')
        ->and($html)->toContain('<span class="lr-line" data-line="1">&lt;b&gt;Qapla\'&lt;/b&gt;</span>');
});

it('renders indented code with the same figure', function () {
    expect(Render::html("Text\n\n    echo 1;\n")->html)->toContain('<figure class="lr-code" data-language="text">');
});

it('parses info-string meta in any order', function () {
    $fence = CodeFence::fromInfo('php {1,3-4} diff title="My diff" showLineNumbers');

    expect($fence->language)->toBe('php')
        ->and($fence->title)->toBe('My diff')
        ->and($fence->highlights)->toBe([1, 3, 4])
        ->and($fence->diff)->toBeTrue()
        ->and($fence->lineNumbers)->toBeTrue()
        ->and(CodeFence::fromInfo('js [app.js]')->title)->toBe('app.js')
        ->and(CodeFence::fromInfo('ts{2}')->highlights)->toBe([2])
        ->and(CodeFence::fromInfo('')->label())->toBe('text');
});

it('caches highlighted output per code block', function () {
    $cache = new Repository(new ArrayStore);
    $highlighter = new CodeHighlighter($cache);
    $fence = new CodeFence('php');

    $first = $highlighter->highlight("echo 'hi';", $fence);
    $keys = array_keys($cache->getStore()->all());

    expect($keys)->toHaveCount(1)
        ->and($keys[0])->toStartWith(CacheFormat::psrPrefix().'code.')
        ->and((new CodeHighlighter($cache))->highlight("echo 'hi';", $fence))->toBe($first);

    $cache->put($keys[0], 'from-cache', 60);

    expect((new CodeHighlighter($cache))->highlight("echo 'hi';", $fence))->toBe('from-cache');
});
