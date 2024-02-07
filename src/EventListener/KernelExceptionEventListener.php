<?php

namespace App\EventListener;

use App\Controller\Common\ErrorResponse;
use App\Exception\HttpCompliantExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class KernelExceptionEventListener
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpCompliantExceptionInterface) {
            $event->setResponse($this->getHttpResponse($exception->getHttpResponseBody(), $exception->getHttpCode()));
        }

        if ($exception instanceof HttpException && $exception->getPrevious() instanceof ValidationFailedException) {
            $event->setResponse($this->getHttpResponse($exception->getMessage(), Response::HTTP_BAD_REQUEST));
        }
   }

   private function getHttpResponse($message, $code): Response {
       $errorResponse = new ErrorResponse($message);
       $responseData = $this->serializer->serialize($errorResponse, JsonEncoder::FORMAT);

       return new Response($responseData, $code, ['Content-Type' => 'application/json']);
   }
}
