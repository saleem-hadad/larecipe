<?php

use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use LaRecipe\Domains\References\Services\ReferenceHtml;
use LaRecipe\Domains\References\Services\ReferenceMarkdown;

function sampleRoute(): Reference
{
    return new Reference(
        kind: 'route',
        target: 'orders.store',
        title: 'POST /orders',
        facts: [['Method', 'POST'], ['URI', '/orders'], ['Middleware', 'web, auth']],
        tables: [new ReferenceTable('Validation rules (StoreOrderRequest)', ['Field', 'Rules'], [['email', 'required|email'], ['note', '<script>alert(1)</script>']])],
    );
}

it('renders a route as a small table in agent Markdown', function () {
    expect((new ReferenceMarkdown)->render(sampleRoute()))->toBe(implode("\n", [
        '**Route** `POST /orders`',
        '',
        '| Method | URI | Middleware |',
        '| --- | --- | --- |',
        '| `POST` | `/orders` | `web, auth` |',
        '',
        'Validation rules (StoreOrderRequest):',
        '',
        '| Field | Rules |',
        '| --- | --- |',
        '| `email` | required\|email |',
        '| `note` | <script>alert(1)</script> |',
    ]));
});

it('renders a command as a fenced usage block', function () {
    $command = new Reference('artisan', 'orders:prune', 'orders:prune', 'Prune old orders', usage: 'php artisan orders:prune [--days DAYS] [--] <tenant>');

    expect((new ReferenceMarkdown)->render($command))
        ->toBe("**Command** `orders:prune`: Prune old orders\n\n```bash\nphp artisan orders:prune [--days DAYS] [--] <tenant>\n```");
});

it('escapes every value in server HTML', function () {
    $html = (new ReferenceHtml)->render(sampleRoute());

    expect($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('<caption>Validation rules (StoreOrderRequest)</caption>')
        ->toContain('<th scope="row">Method</th><td><code>POST</code></td>');
});

it('says plainly when a reference no longer resolves', function () {
    expect((new ReferenceHtml)->missing('route', 'orders.<b>'))
        ->toContain('data-missing="true"')
        ->toContain('The route <code>orders.&lt;b&gt;</code> is not defined in this app.');

    expect((new ReferenceMarkdown)->missing('artisan', 'orders:purge'))
        ->toBe("> [!WARNING]\n> The command `orders:purge` is not registered in this app.");
});

it('round-trips through the snapshot format', function () {
    expect(Reference::fromArray(sampleRoute()->toArray()))->toEqual(sampleRoute());
});
