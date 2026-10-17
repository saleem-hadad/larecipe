<?php

namespace App\Webhooks;

class Handler
{
    public function verify(string $signature): bool
    {
        return $signature !== '';
    }
}
