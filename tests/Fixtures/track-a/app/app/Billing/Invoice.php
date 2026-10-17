<?php

namespace App\Billing;

final class Invoice
{
    // #region refund
    public function refund(int $amount): Refund
    {
        // #region guard
        if ($amount <= 0) {
            throw new InvalidAmount($amount);
        }
        // #endregion

        return $this->gateway->refund($this, $amount);
    }
    // #endregion

    public function total(): int
    {
        return $this->lines->sum('amount');
    }
}
