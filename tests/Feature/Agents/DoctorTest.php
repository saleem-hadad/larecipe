<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Tests\Feature\Agents\Support\Sets;

beforeEach(fn () => Sets::withBilling());

it('grades each set and prints a fix for every finding', function () {
    $this->artisan('larecipe:doctor', ['--set' => 'handbook'])
        ->expectsOutputToContain('PASS  llms.txt')
        ->expectsOutputToContain('PASS  Redirects: 1 redirects land on a page.')
        ->expectsOutputToContain('Agent readiness: A')
        ->assertExitCode(0);
});

it('prints the report as JSON', function () {
    Artisan::call('larecipe:doctor', ['--set' => 'billing', '--json' => true]);
    $reports = json_decode(Artisan::output(), true);
    $checks = collect($reports[0]['checks'])->keyBy('id');

    expect($reports[0]['set'])->toBe('billing')
        ->and($reports[0]['grade'])->toBeIn(['A', 'B'])
        ->and($checks->keys()->all())->toBe(['llms-txt', 'llms-txt-links', 'markdown', 'not-found', 'discovery', 'redirects', 'skill-md', 'mcp', 'cache-store'])
        ->and($checks['llms-txt']['status'])->toBe('pass')
        ->and($checks['not-found']['status'])->toBe('pass')
        ->and($checks['mcp']['status'])->toBe('pass')
        ->and($checks['cache-store']['status'])->toBe('skip');
});

it('fails what agents would trip on, and exits non-zero', function () {
    Sets::withBilling(['agents' => ['llms_txt' => false]]);

    Artisan::call('larecipe:doctor', ['--set' => 'billing', '--json' => true]);
    $checks = collect(json_decode(Artisan::output(), true)[0]['checks'])->keyBy('id');

    expect($checks['llms-txt']['status'])->toBe('fail')
        ->and($checks['llms-txt']['fix'])->toContain('agents.llms_txt')
        ->and($checks['redirects']['status'])->toBe('pass');

    $this->artisan('larecipe:doctor', ['--set' => 'billing'])->assertExitCode(1);
    $this->artisan('larecipe:doctor', ['--set' => 'nope'])->assertExitCode(2);
});

it('fails redirects that loop instead of landing on a page', function () {
    Sets::use(['broken' => [
        'route' => ['prefix' => 'broken', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => Sets::fixture('broken')],
    ]]);

    Artisan::call('larecipe:doctor', ['--json' => true]);
    $redirects = collect(json_decode(Artisan::output(), true)[0]['checks'])->firstWhere('id', 'redirects');

    expect($redirects['status'])->toBe('fail')->and($redirects['message'])->toContain('/broken/old');
});
