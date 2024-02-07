<?php

namespace App\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class UnprocessableCardException extends Exception implements HttpCompliantExceptionInterface
{
    public function getHttpCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function getHttpResponseBody(): string
    {
        return $this->getMessage();
    }
}
