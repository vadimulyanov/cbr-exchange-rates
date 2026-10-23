<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Exchange\Dto\RateRequest;
use App\Exchange\Dto\RateResult;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Service\ExchangeRateService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class RateController extends AbstractController
{
    #[Route('/rates', name: 'rates', methods: ['GET'], format: 'json')]
    #[OA\Get(summary: 'Get the CBR rate and its change from the preceding quotation date')]
    #[OA\Parameter(
        name: 'date',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01'),
    )]
    #[OA\Parameter(
        name: 'currency',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'USD'),
    )]
    #[OA\Parameter(
        name: 'base',
        in: 'query',
        schema: new OA\Schema(type: 'string', example: 'EUR', default: CurrencyCode::RUR),
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Units of base currency per one unit of currency',
        content: new OA\JsonContent(ref: new Model(type: RateResult::class)),
    )]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid input')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'A current or previous quotation is unavailable')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Query validation failed')]
    #[OA\Response(response: Response::HTTP_BAD_GATEWAY, description: 'External service is unavailable or returned invalid data')]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Service is unavailable')]
    public function __invoke(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        RateRequest $request,
        ExchangeRateService $exchangeRateService,
    ): JsonResponse {
        return $this->json($exchangeRateService->getRate($request));
    }
}
