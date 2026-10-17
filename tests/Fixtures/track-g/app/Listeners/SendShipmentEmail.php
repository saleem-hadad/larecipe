<?php

namespace TrackGApp\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use TrackGApp\Events\OrderShipped;

class SendShipmentEmail implements ShouldQueue
{
    public function handle(OrderShipped $event): void {}
}
