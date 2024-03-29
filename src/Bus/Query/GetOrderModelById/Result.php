<?php

namespace App\Bus\Query\GetOrderModelById;

class Result
{
    public function __construct(
        private readonly int $id,
        private readonly int $sum,
        private readonly bool $isPaid,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSum(): int
    {
        return $this->sum;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

}
