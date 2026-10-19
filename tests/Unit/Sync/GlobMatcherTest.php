<?php

use LaRecipe\Domains\Sync\DTOs\SourcePattern;
use LaRecipe\Domains\Sync\Services\GlobMatcher;

it('matches sources globs the way people write them', function (string $glob, string $path, bool $expected) {
    expect((new GlobMatcher)->matches($glob, $path))->toBe($expected);
})->with([
    ['app/Billing/**', 'app/Billing/Invoice.php', true],
    ['app/Billing/**', 'app/Billing/Taxes/Rate.php', true],
    ['app/Billing/**', 'app/BillingReport.php', false],
    ['app/*.php', 'app/User.php', true],
    ['app/*.php', 'app/Models/User.php', false],
    ['app/**/*.php', 'app/User.php', true],
    ['app/**/*.php', 'app/Models/User.php', true],
    ['routes/{api,web}.php', 'routes/api.php', true],
    ['routes/{api,web}.php', 'routes/console.php', false],
    ['app/Billing', 'app/Billing/Invoice.php', true],
    ['app/Billing', 'app/BillingReport.php', false],
    ['./routes/billing.php', 'routes/billing.php', true],
    ['routes/api.php#billing', 'routes/api.php', true],
    ['config/?ail.php', 'config/mail.php', true],
    ['src/[A-C]*.php', 'src/Billing.php', true],
    ['src/[A-C]*.php', 'src/Users.php', false],
]);

it('splits a region off a source', function () {
    $pattern = SourcePattern::parse('routes/api.php#billing');

    expect($pattern->glob)->toBe('routes/api.php')
        ->and($pattern->region)->toBe('billing')
        ->and(SourcePattern::parse('app/**')->region)->toBeNull();
});
