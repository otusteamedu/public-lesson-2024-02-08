<?php

namespace App\Controller\Api\PayForOrder\v1;

use App\Bus\Command\PayForOrder\Command as PayForOrderCommand;
use App\Bus\Command\PayForOrder\Handler as CommandHandler;
use App\Bus\Query\GetOrderModelById\Handler as QueryHandler;
use App\Bus\Query\GetOrderModelById\Result as OrderModel;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class Controller
{
    public function __construct(
        private readonly CommandHandler $commandHandler,
        private readonly QueryHandler $queryHandler,
    ) {
    }

    #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] PayForOrderCommand $command): OrderModel
    {
        ($this->commandHandler)($command);

        return ($this->queryHandler)($command->orderId);
    }
}
