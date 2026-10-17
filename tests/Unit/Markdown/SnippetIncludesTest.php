<?php

/*
 * Snippet includes: real code pulled into the docs, resolved only inside the
 * doc set's snippet root, with missing files and regions made loud.
 */

use LaRecipe\Tests\TestCase;
use LaRecipe\Tests\Unit\Markdown\Render;

function snippetContext(): array
{
    return ['snippets_root' => TestCase::fixture('track-a/app')];
}

it('includes a region of a file as a titled code block', function () {
    $rendered = Render::html('<<< @/app/Billing/Invoice.php#refund', snippetContext());

    expect($rendered->html)->toStartWith('<figure class="lr-code" data-language="php" data-title="app/Billing/Invoice.php">')
        ->and(strip_tags($rendered->html))->toStartWith('app/Billing/Invoice.phppublic function refund(int $amount): Refund')
        ->and(strip_tags($rendered->html))->not->toContain('#region')
        ->and(strip_tags($rendered->html))->not->toContain('public function total')
        ->and($rendered->issues)->toBe([])
        ->and($rendered->dependencies)->toHaveKey(realpath(TestCase::fixture('track-a/app/app/Billing/Invoice.php')));
});

it('dedents a nested region and drops inner markers', function () {
    $markdown = Render::markdown('<<< @/app/Billing/Invoice.php#guard', snippetContext());

    expect($markdown)->toBe("```php title=\"app/Billing/Invoice.php\"\nif (\$amount <= 0) {\n    throw new InvalidAmount(\$amount);\n}\n```");
});

it('inlines the region in agent Markdown, markers removed', function () {
    $markdown = Render::markdown('<<< @/app/Billing/Invoice.php#refund', snippetContext());

    expect($markdown)->toStartWith("```php title=\"app/Billing/Invoice.php\"\npublic function refund(int \$amount): Refund\n{\n    if (\$amount <= 0) {")
        ->and($markdown)->not->toContain('#region')
        ->and($markdown)->not->toContain('#endregion');
});

it('includes a whole file and passes meta through', function () {
    $html = Render::html('<<< @/routes/web.php {1} title="Order routes"', snippetContext())->html;

    expect($html)->toContain('data-title="Order routes"')
        ->and($html)->toContain('<span class="lr-line" data-line="1" data-highlighted>')
        ->and(strip_tags($html))->toContain("Route::middleware('auth')")
        ->and(strip_tags($html))->not->toContain('#region');
});

it('shows a visible notice and reports a missing region', function () {
    $rendered = Render::html('<<< @/app/Billing/Invoice.php#nope', snippetContext());

    expect($rendered->html)->toContain('<aside class="lr-callout" data-type="warning" role="note"><div class="lr-callout-label">Snippet not found</div>')
        ->and($rendered->html)->toContain('<code>app/Billing/Invoice.php#nope</code> could not be included: the region &quot;nope&quot; does not exist in that file.')
        ->and($rendered->issues[0])->toMatchArray(['rule' => 'snippets.missing', 'level' => 'error', 'line' => 1])
        ->and($rendered->hasErrors())->toBeTrue();
});

it('reports a missing file', function () {
    $rendered = Render::html("Intro.\n\n<<< @/app/Missing.php", snippetContext());

    expect($rendered->issues[0])->toMatchArray(['rule' => 'snippets.missing', 'line' => 3])
        ->and($rendered->html)->toContain('could not be included: the file does not exist.');
});

it('never includes secrets or files outside the root', function (string $target) {
    $rendered = Render::html("<<< @/{$target}", snippetContext());

    expect($rendered->issues[0]['rule'])->toBe('snippets.denied')
        ->and($rendered->html)->not->toContain('sk_live_never')
        ->and($rendered->html)->not->toContain('not-a-real-key');
})->with([
    'dotenv' => '.env',
    'keys in storage' => 'storage/oauth-private.key',
    'traversal' => '../../docs/larecipe.json',
    'composer auth' => 'auth.json',
]);

it('is disabled without a snippet root', function () {
    $rendered = Render::html('<<< @/app/Billing/Invoice.php#refund', ['snippets_root' => null]);

    expect($rendered->issues[0]['rule'])->toBe('snippets.disabled')
        ->and($rendered->html)->toContain('snippet includes are not enabled for this doc set.');
});

it('resolves includes from the app base path for repository sets by default', function () {
    $rendered = Render::html('<<< @/composer.json');

    expect(base_path('composer.json'))->toBeFile()
        ->and($rendered->issues)->toBe([])
        ->and($rendered->html)->toContain('data-language="json"');
});
