<?php

use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\Services\PageRewriter;
use LaRecipe\Domains\Upgrade\Services\V2Links;

function rewriteV2(string $markdown, bool $relative = false, string $file = 'page.md'): array
{
    return (new PageRewriter)->rewrite($markdown, '2.2', $file, "docs/2.2/{$file}", new V2Links('docs', $relative), ['overview' => 'overview.md', 'guides' => 'guides/index.md', 'guides/deploy' => 'guides/deploy.md']);
}

it('turns v2 alerts into GitHub alerts and drops their icons', function () {
    $result = rewriteV2("> {primary} Read this.\n\n> {danger.fa-close} Careful.\n> Still careful.\n\n> {success} Done.");

    expect($result['body'])->toBe("> [!IMPORTANT]\n> Read this.\n\n> [!CAUTION]\n> Careful.\n> Still careful.\n\n> [!TIP]\n> Done.\n")
        ->and(collect($result['entries'])->where('kind', ReportEntry::DROPPED)->pluck('message')->first())->toContain('fa-close');
});

it('moves anchors onto the heading that follows them', function () {
    $result = rewriteV2("<a name=\"setup\"></a>\n## Setup\n\n<a name='later'></a>\n\n### Later\n\n<a name=\"orphan\"></a>\nNot a heading.");

    expect($result['body'])->toBe("## Setup {#setup}\n\n### Later {#later}\n\nNot a heading.\n")
        ->and(collect($result['entries'])->where('kind', ReportEntry::MANUAL)->pluck('message')->first())->toContain('#orphan');
});

it('turns cards into <Card>, with their content un-indented', function () {
    $result = rewriteV2("<larecipe-card shadow>\n    Some **bold** text\n    <larecipe-progress :value=\"40\"></larecipe-progress>\n</larecipe-card>");

    expect($result['body'])->toBe("<Card>\nSome **bold** text\n</Card>\n");
});

it('turns standalone badges and buttons into self-closing components, inline ones into text', function () {
    $result = rewriteV2(implode("\n", [
        '<larecipe-badge type="success" rounded>New</larecipe-badge>',
        '<larecipe-badge type="primary" circle icon="fa fa-user"></larecipe-badge>',
        '<larecipe-button type="danger" radius="full">Delete</larecipe-button>',
        '<larecipe-button type="primary" tag="a" href="https://example.com">',
        '    Visit <i class="fa fa-external-link"></i>',
        '</larecipe-button>',
        '',
        'Status: <larecipe-badge type="warning">Beta</larecipe-badge> for now.',
    ]));

    expect($result['body'])->toBe(implode("\n", [
        '<Badge label="New" tone="success" />',
        '<Badge tone="info" icon="user" />',
        '<Button label="Delete" tone="danger" />',
        '<Button label="Visit" tone="info" href="https://example.com" />',
        '',
        'Status: Beta for now.',
    ])."\n");
});

it('turns {{route}} links into relative .md links and keeps {{version}} links unless asked', function () {
    $markdown = 'See [overview](/{{route}}/{{version}}/overview#start), [guides](/docs/{{version}}/guides) and [deploy](/docs/{{version}}/guides/deploy).';

    expect(rewriteV2($markdown)['body'])
        ->toBe("See [overview](overview.md#start), [guides](/docs/{{version}}/guides) and [deploy](/docs/{{version}}/guides/deploy).\n");

    expect(rewriteV2($markdown, relative: true, file: 'guides/deploy.md')['body'])
        ->toBe("See [overview](../overview.md#start), [guides](index.md) and [deploy](deploy.md).\n");
});

it('never touches code samples or inline code', function () {
    $markdown = "Use `[x](/{{route}}/{{version}}/overview)` like this:\n\n```markdown\n> {info} Not an alert here.\n<larecipe-card>\n    Raw\n</larecipe-card>\n```";

    expect(rewriteV2($markdown)['body'])->toBe($markdown."\n");
});

it('seeds the title and description, and drops a hand-written table of contents', function () {
    $result = rewriteV2("# Install {#install}\n\nAdd **Acme** to your app.\nIt takes a minute.\n\n---\n\n- [Requirements](#requirements)\n- [Steps](#steps)\n\n## Requirements\n\nPHP.");

    expect($result['title'])->toBe('Install')
        ->and($result['description'])->toBe('Add Acme to your app. It takes a minute.')
        ->and($result['body'])->toBe("# Install {#install}\n\n## Requirements\n\nPHP.\n");
});

it('keeps an opening paragraph that links somewhere in the article', function () {
    $result = rewriteV2("# Install\n\nRead the [overview](overview.md) first.\n");

    expect($result['description'])->toBe('Read the overview first.')
        ->and($result['body'])->toContain('Read the [overview](overview.md) first.');
});

it('turns a plain HTML list into a Markdown list', function () {
    expect(rewriteV2("Who:\n<ul>\n<li>guests</li>\n <li>admins</li>\n</ul>\nAnd more.")['body'])
        ->toBe("Who:\n\n- guests\n- admins\n\nAnd more.\n");
});

it('reports Blade, custom Vue components and raw HTML for a person', function () {
    $entries = collect(rewriteV2("@if(auth()->check())\nHi {{ \$user->name }}\n@endif\n\n<my-widget :items=\"3\"></my-widget>\n\n<div class=\"note\">Raw</div>\n\nLeave {{version}} alone.")['entries'])
        ->where('kind', ReportEntry::MANUAL);

    expect($entries->pluck('category')->all())->toBe(['blade', 'blade', 'blade', 'vue', 'html'])
        ->and($entries->pluck('line')->all())->toBe([1, 2, 3, 5, 7]);
});

it('removes progress bars and stray closing tags with a report entry', function () {
    $result = rewriteV2("</larecipe-card>\n<larecipe-progress type=\"success\" :value=\"100\"></larecipe-progress>\nText.");

    expect($result['body'])->toBe("Text.\n")
        ->and(collect($result['entries'])->pluck('message')->implode(' '))->toContain('closed no card')->toContain('progress');
});
