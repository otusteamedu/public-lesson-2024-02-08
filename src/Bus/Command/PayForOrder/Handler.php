<?php

namespace App\Bus\Command\PayForOrder;

use App\Entity\Order;
use App\Exception\AcquiringException;
use App\Exception\UnprocessableCardException;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;

class Handler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * @param Command $command
     * @throws UnprocessableCardException
     */
    public function __invoke(Command $command): void
    {
        try {
            $this->orderService->makePayment($command->sum, $command->cardNumber, $command->owner, $command->cvv);
        } catch (AcquiringException $e) {
            throw new UnprocessableCardException($e->getMessage());
        }

        /** @var Order $order */
        $order = $this->entityManager->getRepository(Order::class)->find($command->orderId);
        $order->setIsPaid(true);
        $this->entityManager->flush();
    }
}
