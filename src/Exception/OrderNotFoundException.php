<?php

namespace App\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class OrderNotFoundException extends Exception implements HttpCompliantExceptionInterface
{
    public function getHttpCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getHttpResponseBody(): string
    {
        return 'Order not found';
    }
}
