# Doctrine. Дополнительные возможности

## Готовим проект

1. Запускаем контейнеры командой `docker-compose up -d`
2. Входим в контейнер командой `docker exec -it php sh`. Дальнейшие команды будем выполнять из контейнера
3. Устанавливаем зависимости командой `composer install`
4. Выполняем миграции командой `php bin/console doctrine:migrations:migrate`

## Проверяем работоспособность приложения

1. Выполняем запрос Not existing order из Postman-коллекции, получаем код ответа 404.
2. Выполняем любой запрос из каталога Bad Request из Postman-коллекции, получаем код ответа 400.
3. Выполняем любой запрос из каталога AcquiringException из Postman-коллекции, получаем код ответа 422.
4. Выполняем запрос OK order из Postman-коллекции, получаем код ответа 200. Видим, что в БД в заказе с `id = 1`
   поменялось значение поля `is_paid` на `true`

## Десериализуем Request в DTO и добавляем валидацию

1. Устанавливаем пакет `symfony/serializer-pack`
2. Добавляем класс `App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1\Input;
    
    use Symfony\Component\Validator\Constraints as Assert;
    
    class OrderPaymentData
    {
        public function __construct(
            public readonly int $orderId,
            public readonly int $sum,
            #[Assert\Length(16)]
            public readonly string $cardNumber,
            #[Assert\NotEqualTo('Fake')]
            public readonly string $owner,
            #[Assert\Length(3)]
            public readonly string $cvv,
        ) {
        }
    }
    ```
3. Исправляем класс `App\Controller\Api\PayForOrder\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1;
    
    use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
    use App\Entity\Order;
    use App\Exception\AcquiringException;
    use App\Service\OrderService;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Annotation\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly OrderService $orderService,
        ) {
        }
    
        #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Response
        {
            /** @var Order|null $order */
            $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);
            if ($order === null) {
                return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }
    
            if ($order->isPaid() || $order->isCancelled()) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid order status'], Response::HTTP_BAD_REQUEST);
            }
    
            if ($orderPaymentData->sum !== $order->getSum()) {
                return new JsonResponse(['success' => false, 'message' => 'Wrong sum provided'], Response::HTTP_BAD_REQUEST);
            }
    
            try {
                $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
            } catch (AcquiringException $e) {
                return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $order->setIsPaid(true);
            $this->entityManager->flush();
    
            return new JsonResponse(['success' => true]);
        }
    }
    ```
4. Выполняем запрос AcquiringException/Invalid card number из Postman-коллекции, получаем код ответа 422, но ошибку
   выдаёт уже пакет `symfony/validator`.

## Добавляем более сложную валидацию

1. Добавляем класс `App\Validation\OrderSumAndStatusConstraint`
    ```php
    <?php
    
    namespace App\Validation;
    
    use Attribute;
    use Symfony\Component\Validator\Constraint;
    
    #[Attribute]
    class OrderSumAndStatusConstraint extends Constraint
    {
        public function __construct(
            array $groups = null,
            $payload = null
        ) {
            parent::__construct([], $groups, $payload);
        }

        public function getTargets(): string|array
        {
            return self::CLASS_CONSTRAINT;
        }
    }
    ```
2. Добавляем класс `App\Validation\OrderSumAndStatusConstraintValidator`
    ```php
    <?php
    
    namespace App\Validation;
    
    use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
    use App\Entity\Order;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\Validator\Constraint;
    use Symfony\Component\Validator\ConstraintValidator;
    use Symfony\Component\Validator\Exception\UnexpectedTypeException;
    use Symfony\Component\Validator\Exception\UnexpectedValueException;
    
    class OrderSumAndStatusConstraintValidator extends ConstraintValidator
    {
        public function __construct(private readonly EntityManagerInterface $entityManager)
        {
        }
    
        public function validate(mixed $value, Constraint $constraint)
        {
            if (!$constraint instanceof OrderSumAndStatusConstraint) {
                throw new UnexpectedTypeException($constraint, OrderSumAndStatusConstraint::class);
            }
    
            if (!$value instanceof OrderPaymentData) {
                throw new UnexpectedValueException($value, OrderPaymentData::class);
            }
    
            $order = $this->entityManager->getRepository(Order::class)->find($value->orderId);
            if ($order === null) {
                $this->context->buildViolation('Order not found')->addViolation();
                
                return;
            }
    
            if ($order->isPaid() || $order->isCancelled()) {
                $this->context->buildViolation('Invalid order status')->addViolation();
            }
    
            if ($value->sum !== $order->getSum()) {
                $this->context->buildViolation('Wrong sum provided')->addViolation();
            }
        }
    }
    
    ```
3. К классу `App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData` добавляем атрибут
   `App\Validation\OrderSumAndStatusConstraint`
4. Исправляем класс `App\Controller\Api\PayForOrder\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1;
    
    use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
    use App\Entity\Order;
    use App\Exception\AcquiringException;
    use App\Service\OrderService;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Annotation\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly OrderService $orderService,
        ) {
        }
    
        #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Response
        {
            /** @var Order|null $order */
            $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);
            if ($order === null) {
                return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }
    
            try {
                $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
            } catch (AcquiringException $e) {
                return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $order->setIsPaid(true);
            $this->entityManager->flush();
    
            return new JsonResponse(['success' => true]);
        }
    }
    ```
5. Выполняем запрос Bad Request/Already paid order из Postman-коллекции, получаем код ответа 422, и ошибку
  от пакета `symfony/validator`.

## Добавляем сериализацию в исходящий DTO

1. Добавляем трейт `App\Controller\Common\ResultTrait`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    trait ResultTrait
    {
        private bool $success;
    
        private ?string $message = null;
    
        public function setSuccess(bool $success): void
        {
            $this->success = $success;
        }
    
        public function setMessage(?string $message): void
        {
            $this->message = $message;
        }
    
        public function isSuccess(): bool
        {
            return $this->success;
        }
    
        public function getMessage(): ?string
        {
            return $this->message;
        }
    }
    ```
2. Добавляем класс `App\Controller\Api\PayForOrder\v1\Output\OrderData`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1\Output;
    
    class OrderData
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
    ```
3. Добавляем класс `App\Controller\Api\PayForOrder\v1\Output\SuccessResponse`
    ```php
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
    ```
4. Добавляем класс `App\EventListener\KernelViewEventListener`
    ```php
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
    ```
5. В файл `config/services.yaml` добавляем описание нового сервиса
    ```yaml
    App\EventListener\KernelViewEventListener:
        tags:
            - { name: kernel.event_listener, event: kernel.view }
    ```
6. Исправляем класс `App\Controller\Api\PayForOrder\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1;
    
    use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
    use App\Entity\Order;
    use App\Exception\AcquiringException;
    use App\Service\OrderService;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\AsController;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Routing\Annotation\Route;
    
    #[AsController]
    class Controller
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly OrderService $orderService,
        ) {
        }
    
        #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
        public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Order|Response
        {
            /** @var Order|null $order */
            $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);
            if ($order === null) {
                return new JsonResponse(['success' => false, 'message' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }
    
            try {
                $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
            } catch (AcquiringException $e) {
                return new JsonResponse(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
    
            $order->setIsPaid(true);
            $this->entityManager->flush();
    
            return $order;
        }
    }
    ```
7. Выполняем запрос OK order из Postman-коллекции, получаем код ответа 200 с параметрами `orderId = 2` и `sum = 200`.
   Видим ответ с кодом 200 и требуемым содержимым.

## Добавляем преобразование исключений в валидные HTTP-ответы

1. Добавляем интерфейс `App\Exception\HttpCompliantExceptionInterface`
    ```php
    <?php
    
    namespace App\Exception;
    
    interface HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int;
        
        public function getHttpResponseBody(): string;
    }
    ```
2. Добавляем класс `App\Exception\EntityNotFoundException`
    ```php
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
    ```
3. Добавляем класс `App\Exception\ValidationException`
    ```php
    <?php
    
    namespace App\Exception;
    
    use Exception;
    use Symfony\Component\HttpFoundation\Response;
    
    class ValidationException extends Exception implements HttpCompliantExceptionInterface
    {
        public function getHttpCode(): int
        {
            return Response::HTTP_BAD_REQUEST;
        }
    
        public function getHttpResponseBody(): string
        {
            return $this->getMessage();
        }
    }
    
    ```
4. Добавляем класс `App\Exception\UnprocessableCardException`
    ```php
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
    ```
5. Добавляем класс `App\Controller\Common\ErrorResponse`
    ```php
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
    ```
6. Добавляем класс `App\EventListener\KernelExceptionEventListener`
    ```php
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
    ```
7. В файл `config/services.yaml` добавляем описание нового сервиса
    ```yaml
    App\EventListener\KernelExceptionEventListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
    ```
8. Добавляем класс `App\Validation\OrderSumAndStatusConstraintValidator`
    ```php
    <?php
    
    namespace App\Validation;
    
    use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
    use App\Entity\Order;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\Validator\Constraint;
    use Symfony\Component\Validator\ConstraintValidator;
    use Symfony\Component\Validator\Exception\UnexpectedTypeException;
    use Symfony\Component\Validator\Exception\UnexpectedValueException;
    
    class OrderSumAndStatusConstraintValidator extends ConstraintValidator
    {
        public function __construct(private readonly EntityManagerInterface $entityManager)
        {
        }
    
        public function validate(mixed $value, Constraint $constraint)
        {
            if (!$constraint instanceof OrderSumAndStatusConstraint) {
                throw new UnexpectedTypeException($constraint, OrderSumAndStatusConstraint::class);
            }
    
            if (!$value instanceof OrderPaymentData) {
                throw new UnexpectedValueException($value, OrderPaymentData::class);
            }
    
            $order = $this->entityManager->getRepository(Order::class)->find($value->orderId);
            if ($order === null) {
                throw new OrderNotFoundException();
            }
    
            if ($order->isPaid() || $order->isCancelled()) {
                $this->context->buildViolation('Invalid order status')->addViolation();
            }
    
            if ($value->sum !== $order->getSum()) {
                $this->context->buildViolation('Wrong sum provided')->addViolation();
            }
        }
    }
    
    ```
9. Исправляем класс `App\Controller\Api\PayForOrder\v1\Controller`
     ```php
     <?php
    
     namespace App\Controller\Api\PayForOrder\v1;
    
     use App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData;
     use App\Entity\Order;
     use App\Exception\AcquiringException;
     use App\Exception\OrderNotFoundException;
     use App\Exception\UnprocessableCardException;
     use App\Service\OrderService;
     use Doctrine\ORM\EntityManagerInterface;
     use Symfony\Component\HttpFoundation\Response;
     use Symfony\Component\HttpKernel\Attribute\AsController;
     use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
     use Symfony\Component\Routing\Annotation\Route;
    
     #[AsController]
     class Controller
     {
         public function __construct(
             private readonly EntityManagerInterface $entityManager,
             private readonly OrderService $orderService,
         ) {
         }
    
         #[Route(path: '/api/pay-for-order/v1', methods: ['POST'])]
         public function __invoke(#[MapRequestPayload] OrderPaymentData $orderPaymentData): Order
         {
             /** @var Order $order */
             $order = $this->entityManager->getRepository(Order::class)->find($orderPaymentData->orderId);
    
             try {
                 $this->orderService->makePayment($orderPaymentData->sum, $orderPaymentData->cardNumber, $orderPaymentData->owner, $orderPaymentData->cvv);
             } catch (AcquiringException $e) {
                 throw new UnprocessableCardException($e->getMessage());
             }
    
             $order->setIsPaid(true);
             $this->entityManager->flush();
    
             return $order;
         }
     }
     ```
10. Выполняем запрос Not existing order из Postman-коллекции, получаем код ответа 404 с нужным телом ответа.
11. Выполняем любой запрос из каталога Bad Request из Postman-коллекции, получаем код ответа 400 с нужным телом ответа.
12. Выполняем запрос AcquiringException/Invalid card number из Postman-коллекции, получаем код ответа
    400 с нужным телом ответа.
13. Выполняем запрос AcquiringException/Not Enough money из Postman-коллекции, получаем код ответа 422 с нужным телом
    ответа.

## Выносим бизнес-логику с помощью CQRS

1. Переименовываем класс `App\Controller\Api\PayForOrder\v1\Input\OrderPaymentData` в
   `App\Bus\Command\PayForOrder\Command`
2. Добавляем класс `App\Bus\Command\PayForOrder\Handler`
    ```php
    <?php
    
    namespace App\Bus\Command\PayForOrder;
    
    use App\Entity\Order;
    use App\Exception\AcquiringException;
    use App\Exception\UnprocessableCardException;
    use App\Service\OrderService;
    use Doctrine\ORM\EntityManagerInterface;
    
    class Handler
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly OrderService $orderService,
        ) {
        }
    
        /**
         * @param Command $command
         * @throws UnprocessableCardException
         */
        public function __invoke(Command $command): void
        {
            try {
                $this->orderService->makePayment($command->sum, $command->cardNumber, $command->owner, $command->cvv);
            } catch (AcquiringException $e) {
                throw new UnprocessableCardException($e->getMessage());
            }
    
            /** @var Order $order */
            $order = $this->entityManager->getRepository(Order::class)->find($command->orderId);
            $order->setIsPaid(true);
            $this->entityManager->flush();
        }
    } 
    ```
3. Переименовываем класс `App\Controller\Api\PayForOrder\v1\Output\OrderData` в `App\Bus\Query\GetOrderModelById\Result`
4. Исправляем класс `App\Controller\Api\PayForOrder\v1\Output\SuccessResponse`
    ```php
    <?php
    
    namespace App\Controller\Api\PayForOrder\v1\Output;
    
    use App\Bus\Query\GetOrderById\Result;
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
    ```
5. Добавляем класс `App\Bus\Query\GetOrderModelById\Handler`
    ```php
    <?php
    
    namespace App\Bus\Query\GetOrderModelById;
    
    use App\Entity\Order;
    use App\Exception\OrderNotFoundException;
    use Doctrine\ORM\EntityManagerInterface;
    
    class Handler
    {
        public function __construct(private readonly EntityManagerInterface $entityManager) {
        }
    
        public function __invoke(int $id): Result
        {
            /** @var Order|null $order */
            $order = $this->entityManager->getRepository(Order::class)->find($id);
            if ($order === null) {
                throw new OrderNotFoundException();
            }
            
            return new Result($order->getId(), $order->getSum(), $order->isPaid());
        }
    }
    ```
6. Исправляем класс `App\EventListener\KernelViewEventListener`
    ```php
    <?php
    
    namespace App\EventListener;
    
    use App\Bus\Query\GetOrderModelById\Result as OrderModel;
    use App\Controller\Api\PayForOrder\v1\Output\SuccessResponse;
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
    
            if ($value instanceof OrderModel) {
                $event->setResponse($this->getHttpResponse(new SuccessResponse($value)));
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
    ```
7. Исправляем класс `App\Controller\Api\PayForOrder\v1\Controller`
    ```php
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
    ```
8. Выполняем запрос Not existing order из Postman-коллекции, получаем код ответа 404.
9. Выполняем любой запрос из каталога Bad Request из Postman-коллекции, получаем код ответа 400.
12. Выполняем запрос AcquiringException/Invalid card number из Postman-коллекции, получаем код ответа 400.
13. Выполняем запрос AcquiringException/Not Enough money из Postman-коллекции, получаем код ответа 422.
11. Выполняем запрос OK order из Postman-коллекции, получаем код ответа 200.
