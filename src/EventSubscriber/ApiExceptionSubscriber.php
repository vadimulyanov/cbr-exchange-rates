<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Dto\ApiError;
use App\Exchange\Exception\RateNotFoundException;
use App\Exchange\Exception\SourceUnavailableException;
use Doctrine\DBAL\Exception as DatabaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private const string PATH_API = '/api/';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Обрабатываем ошибку до штатного ErrorListener, чтобы не дублировать запись в логе
        return [KernelEvents::EXCEPTION => ['onException', 1]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), self::PATH_API)) {
            return;
        }

        $error = $event->getThrowable();
        $apiError = $this->getApiError($error);

        if ($apiError->status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->error('api.request.failed', [
                'exception' => $error,
                'status'    => $apiError->status,
                'method'    => $event->getRequest()->getMethod(),
                'path'      => $event->getRequest()->getPathInfo(),
            ]);
        }

        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];

        $event->setResponse(
            new JsonResponse(
                ['error' => $apiError->message],
                $apiError->status,
                $headers,
            ),
        );
    }

    private function getApiError(\Throwable $error): ApiError
    {
        return match (true) {
            $error instanceof RateNotFoundException => new ApiError(
                Response::HTTP_NOT_FOUND,
                $error->getMessage(),
            ),
            $error instanceof SourceUnavailableException => new ApiError(
                Response::HTTP_BAD_GATEWAY,
                $error->getMessage(),
            ),
            $error instanceof DatabaseException => new ApiError(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'Service is temporarily unavailable',
            ),
            $error instanceof \InvalidArgumentException => new ApiError(
                Response::HTTP_BAD_REQUEST,
                $error->getMessage(),
            ),
            $error instanceof HttpExceptionInterface => new ApiError(
                $error->getStatusCode(),
                $this->getHttpErrorMessage($error->getStatusCode()),
            ),
            default => new ApiError(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'An internal error occurred',
            ),
        };
    }

    private function getHttpErrorMessage(int $status): string
    {
        if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            return 'An internal error occurred';
        }

        return match ($status) {
            Response::HTTP_NOT_FOUND          => 'Resource not found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed',
            default                           => 'Invalid request parameters',
        };
    }
}
