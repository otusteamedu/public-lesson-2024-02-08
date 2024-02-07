<?php

namespace App\Controller\Api\PayForOrder\v1\Output;

use App\Controller\Common\ResultTrait;

class SuccessResponse
{
    use ResultTrait;

    public function __construct(private readonly OrderData $order)
    {
        $this->setSuccess(true);
    }

    public function getOrder(): OrderData
    {
        return $this->order;
    }
}
