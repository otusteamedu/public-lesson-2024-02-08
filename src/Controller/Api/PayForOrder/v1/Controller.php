<?php

namespace App\Controller\Api\PayForOrder\v1;

use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
use App\Entity\Order;
use App\Exception\AcquiringException;
use App\Exception\OrderNotFoundException;
use App\Exception\UnprocessableCardException;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderService $orderService,
    ) {
    }

    #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Order
    {
        /** @var Order $order */
        $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);

        try {
            $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
        } catch (AcquiringException $e) {
            throw new UnprocessableCardException($e->getMessage());
        }

        $order->setIsPaid(true);
        $this->entityManager->flush();

        return $order;
    }
}
