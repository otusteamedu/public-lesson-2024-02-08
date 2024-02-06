<?php

namespace App\Controller\Api\PayForOrder\v1;

use App\Entity\Order;
use App\Exception\AcquiringException;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderService $orderService,
    )
    {
    }

    #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $orderId = (int)$request->request->get('orderId');
        $sum = (int)$request->request->get('sum');
        $cardNumber = $request->request->get('cardNumber');
        $owner = $request->request->get('owner');
        $cvv = $request->request->get('cvv');

        /** @var Order $order */
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);
        if ($order === null) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($order->isPaid() || $order->isCancelled()) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid order status'], Response::HTTP_BAD_REQUEST);
        }

        if ($sum !== $order->getSum()) {
            return new JsonResponse(['success' => false, 'message' => 'Wrong sum provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->orderService->makePayment($sum, $cardNumber, $owner, $cvv);
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
