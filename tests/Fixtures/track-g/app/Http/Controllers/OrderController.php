<?php

namespace TrackGApp\Http\Controllers;

use TrackGApp\Http\Requests\StoreOrderRequest;

class OrderController
{
    public function __construct()
    {
        throw new \RuntimeException('Live references must never build the controller.');
    }

    public function store(StoreOrderRequest $request): string
    {
        return 'stored';
    }
}
