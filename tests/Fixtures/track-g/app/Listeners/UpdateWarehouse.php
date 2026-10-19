<?php

namespace TrackGApp\Listeners;

use TrackGApp\Events\OrderShipped;

class UpdateWarehouse
{
    public function handle(OrderShipped $event): void {}
}
