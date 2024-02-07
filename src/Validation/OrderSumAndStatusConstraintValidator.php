<?php

namespace App\Validation;

use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class OrderSumAndStatusConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof OrderSumAndStatusConstraint) {
            throw new UnexpectedTypeException($constraint, OrderSumAndStatusConstraint::class);
        }

        if (!$value instanceof OrderPaymentData) {
            throw new UnexpectedValueException($value, OrderPaymentData::class);
        }

        $order = $this->entityManager->getRepository(Order::class)->find($value->orderId);
        if ($order === null) {
            throw new OrderNotFoundException();
        }

        if ($order->isPaid() || $order->isCancelled()) {
            $this->context->buildViolation('Invalid order status')->addViolation();
        }

        if ($value->sum !== $order->getSum()) {
            $this->context->buildViolation('Wrong sum provided')->addViolation();
        }
    }
}
