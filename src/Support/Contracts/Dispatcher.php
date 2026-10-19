<?php

namespace LaRecipe\Support\Contracts;

/**
 * How portable domains publish events without depending on Laravel.
 *
 * The Laravel adapter forwards to the framework's event dispatcher; the future
 * standalone CLI ships its own.
 */
interface Dispatcher
{
    public function dispatch(object $event): void;
}
