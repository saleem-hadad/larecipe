<?php

namespace App\Webhooks;

class Handler
{
    public function receive(array $payload): void
    {
        $this->log($payload);
    }

    // #region verify
    public function verify(string $signature): bool
    {
        return $signature !== '';
    }
    // #endregion

    private function log(array $payload): void
    {
        //
    }
}
