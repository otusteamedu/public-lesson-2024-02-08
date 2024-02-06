<?php

namespace App\Validation;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
class OrderSumAndStatusConstraint extends Constraint
{
    public function __construct(
        array $groups = null,
        $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
