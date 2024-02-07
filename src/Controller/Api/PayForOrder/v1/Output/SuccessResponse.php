<?php

namespace App\Controller\Api\PayForOrder\v1\Output;

use App\Bus\Query\GetOrderModelById\Result;
use App\Controller\Common\ResultTrait;

class SuccessResponse
{
    use ResultTrait;

    public function __construct(private readonly Result $order)
    {
        $this->setSuccess(true);
    }

    public function getOrder(): Result
    {
        return $this->order;
    }
}
