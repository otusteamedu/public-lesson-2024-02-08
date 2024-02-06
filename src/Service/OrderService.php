<?php

namespace App\Service;

use App\Exception\AcquiringException;

class OrderService
{
    /**
     * @throws AcquiringException
     */
    public function makePayment(int $sum, string $cardNumber, string $owner, string $cvv): void
    {
        if ($sum >= 600) {
            throw new AcquiringException('Not enough money');
        }
        if (strlen($cardNumber) !== 16) {
            throw new AcquiringException('Invalid card number');
        }
        if ($owner === 'Fake') {
            throw new AcquiringException('Invalid owner');
        }
        if (strlen($cvv) !== 3) {
            throw new AcquiringException('Invalid cvv');
        }
    }
}
