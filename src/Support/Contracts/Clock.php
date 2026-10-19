<?php

namespace LaRecipe\Support\Contracts;

use DateTimeImmutable;

/**
 * The only way portable domains read the current time, so tests can freeze it.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
