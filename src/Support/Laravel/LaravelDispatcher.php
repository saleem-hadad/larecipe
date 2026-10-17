<?php

namespace LaRecipe\Support\Laravel;

use Illuminate\Contracts\Events\Dispatcher as Events;
use LaRecipe\Support\Contracts\Dispatcher;

final class LaravelDispatcher implements Dispatcher
{
    public function __construct(private readonly Events $events) {}

    public function dispatch(object $event): void
    {
        $this->events->dispatch($event);
    }
}
