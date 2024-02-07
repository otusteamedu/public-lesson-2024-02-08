<?php

namespace App\EventListener;

use App\Controller\Api\PayForOrder\v1\Output\OrderData;
use App\Controller\Api\PayForOrder\v1\Output\SuccessResponse;
use App\Entity\Order;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class KernelViewEventListener
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function onKernelView(ViewEvent $event): void
    {
        $value = $event->getControllerResult();

        if ($value instanceof Order) {
            $orderData = new OrderData($value->getId(), $value->getSum(), $value->isPaid());
            $successResponse = new SuccessResponse($orderData);
            $event->setResponse($this->getHttpResponse($successResponse));
        }
   }

   private function getHttpResponse(mixed $successResponse): Response {
       $responseData = $this->serializer->serialize(
           $successResponse,
           JsonEncoder::FORMAT,
           [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]
       );

       return new Response($responseData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
   }
}
