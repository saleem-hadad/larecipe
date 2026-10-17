<?php

namespace TrackGApp\Events;

use TrackGApp\Models\Order;

class OrderShipped
{
    public function __construct(
        public Order $order,
        public string $trackingNumber,
        public ?string $carrier = null,
    ) {}
}
