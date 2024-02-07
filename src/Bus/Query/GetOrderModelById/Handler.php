<?php

namespace App\Bus\Query\GetOrderModelById;

use App\Entity\Order;
use App\Exception\OrderNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class Handler
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {
    }

    public function __invoke(int $id): Result
    {
        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->find($id);
        if ($order === null) {
            throw new OrderNotFoundException();
        }

        return new Result($order->getId(), $order->getSum(), $order->isPaid());
    }
}
