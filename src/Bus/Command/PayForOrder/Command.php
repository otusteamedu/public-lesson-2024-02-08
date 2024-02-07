<?php

namespace App\Bus\Command\PayForOrder;

use App\Validation\OrderSumAndStatusConstraint;
use Symfony\Component\Validator\Constraints as Assert;

#[OrderSumAndStatusConstraint]
class Command
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $sum,
        #[Assert\Length(16)]
        public readonly string $cardNumber,
        #[Assert\NotEqualTo('Fake')]
        public readonly string $owner,
        #[Assert\Length(3)]
        public readonly string $cvv,
    ) {
    }
}
