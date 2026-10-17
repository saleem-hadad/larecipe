<?php

use LaRecipe\Domains\Search\Services\Tokenizer;

it('keeps code identifiers searchable whole and by their parts', function (string $text, array $expected) {
    expect((new Tokenizer)->tokenize($text))->toBe($expected);
})->with([
    'static call' => ['Route::middleware', ['route', 'middleware', 'route::middleware']],
    'method call' => ['$request->user()', ['request', 'user', 'request->user']],
    'artisan command' => ['php artisan orders:prune', ['php', 'artisan', 'orders', 'prune', 'orders:prune']],
    'snake_case' => ['billing_email', ['billing', 'email', 'billing_email']],
    'kebab-case' => ['kebab-case', ['kebab', 'case', 'kebab-case']],
    'camelCase' => ['hasMany', ['has', 'many', 'hasmany']],
    'namespace' => ['App\Models\User', ['app', 'models', 'user', 'app\models\user']],
    'prose' => ['Verify the signature, then retry.', ['verify', 'the', 'signature', 'then', 'retry']],
    'arabic' => ['الدليل الحالي', ['الدليل', 'الحالي']],
]);

it('drops single letters but keeps numbers', function () {
    expect((new Tokenizer)->tokenize('a 5 minute x retry'))->toBe(['5', 'minute', 'retry']);
});

it('splits a query into terms and whole identifiers, without stop words', function () {
    expect((new Tokenizer)->query('how to use Route::middleware'))->toBe([
        'terms' => ['use', 'route', 'middleware'],
        'wholes' => ['route::middleware'],
    ])->and((new Tokenizer)->query('the')['terms'])->toBe(['the']);
});

it('lists identifier parts for the browser index', function () {
    expect((new Tokenizer)->expansions('Call hasMany on $request->user() today'))->toBe('has many request user');
});
