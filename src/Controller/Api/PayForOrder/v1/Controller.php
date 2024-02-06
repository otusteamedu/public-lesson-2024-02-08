<?php

namespace App\Controller\Api\PayForOrder\v1;

use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
use App\Entity\Order;
use App\Exception\AcquiringException;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Response
    {
        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);
        if ($order === null) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->isPaid() || $order->isCancelled()) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid order status'], Response::HTTP_BAD_REQUEST);
        }

        if ($orderPaymentData->sum !== $order->getSum()) {
            return new JsonResponse(['success' => false, 'message' => 'Wrong sum provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
        } catch (AcquiringException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->setIsPaid(true);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'success' => true,
                'order' => ['id' => $order->getId(), 'sum' => $order->getSum(), 'isPaid' => $order->isPaid()]
            ]
        );
    }
}
