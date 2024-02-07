<?php

namespace App\Controller\Common;

class ErrorResponse
{
    use ResultTrait;

    public function __construct(string $message)
    {
        $this->setSuccess(false);
        $this->setMessage($message);
    }
}
