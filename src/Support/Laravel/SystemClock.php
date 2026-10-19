<?php

namespace LaRecipe\Support\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use LaRecipe\Support\Contracts\Clock;

/**
 * Reads time through Laravel's Date facade, so Carbon::setTestNow() freezes it in tests.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Date::now()->toDateTimeImmutable();
    }
}
