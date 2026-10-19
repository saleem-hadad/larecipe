<?php

use LaRecipe\Domains\Access\Actions\FilterGatedSourceAction;
use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;

function gatedPage(string $body): Page
{
    return Page::make(new PageRef('docs', 'current', 'en', 'gated'), new Frontmatter(['title' => 'Gated']), $body);
}

it('cuts denied blocks and unwraps allowed ones, line for line with the AST', function () {
    $page = gatedPage(implode("\n", [
        '# Gated',
        '',
        'Before.',
        '<Can ability="a">',
        'Allowed for A.',
        '<Cannot ability="b">',
        'Only without B.',
        '</Cannot>',
        '</Can>',
        'After.',
    ]));

    $filter = app(FilterGatedSourceAction::class);

    expect($filter->execute($page, Audience::granting(['a', 'b']))->body)
        ->toBe("# Gated\n\nBefore.\n\nAllowed for A.\n\nAfter.");

    expect($filter->execute($page, Audience::guest())->body)
        ->toBe("# Gated\n\nBefore.\n\nAfter.");

    expect($filter->execute($page, Audience::granting(['a']))->body)
        ->toBe("# Gated\n\nBefore.\n\nAllowed for A.\n\nOnly without B.\n\nAfter.");
});

it('keeps quotes and lists intact around a removed block', function () {
    $page = gatedPage(implode("\n", [
        '> Quoted.',
        '>',
        '> <Can ability="a">',
        '> Secret quote.',
        '> </Can>',
        '',
        '- Item',
        '',
        '  <Can ability="a">',
        '  Secret item.',
        '  </Can>',
    ]));

    $body = app(FilterGatedSourceAction::class)->execute($page, Audience::guest())->body;

    expect($body)->not->toContain('Secret')->toStartWith("> Quoted.\n>")->toContain('- Item');
});

it('returns the page untouched when it has no gate blocks or the audience is unrestricted', function () {
    $page = gatedPage("# Plain\n\nNothing gated.\n");

    expect(app(FilterGatedSourceAction::class)->execute($page, Audience::guest()))->toBe($page);

    $gated = gatedPage("<Can ability=\"a\">\nAll of it.\n</Can>\n");
    expect(app(FilterGatedSourceAction::class)->execute($gated, Audience::unrestricted()))->toBe($gated);
});

it('never drops a content line when a block is left unclosed', function () {
    $page = gatedPage("Intro.\n\n<Can ability=\"a\">\nRest of the page.");

    expect(app(FilterGatedSourceAction::class)->execute($page, Audience::granting(['a']))->body)
        ->toBe("Intro.\n\nRest of the page.");
});
