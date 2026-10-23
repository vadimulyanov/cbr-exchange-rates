<?php

declare(strict_types=1);

namespace App\Cbr\Client;

use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Model\HistoryRecord;
use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\DateRange;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CbrClient
{
    private const string CURRENCY_CATALOG_PATH = '/scripts/XML_valFull.asp';
    private const string HISTORY_RATES_PATH = '/scripts/XML_dynamic.asp';

    private const string DATE_FORMAT = 'd/m/Y';

    public function __construct(
        private HttpClientInterface $httpClient,
        private XmlParser $xmlParser,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<CurrencyRecord>
     */
    public function getCurrencies(): array
    {
        return $this->parse(
            fn () => $this->xmlParser->getCurrencies(
                $this->request(self::CURRENCY_CATALOG_PATH),
            ),
        );
    }

    /**
     * @return list<HistoryRecord>
     */
    public function history(string $sourceId, DateRange $range): array
    {
        return $this->parse(
            fn () => $this->xmlParser->history(
                $this->request(self::HISTORY_RATES_PATH, [
                    'date_req1' => $range->from->value->format(self::DATE_FORMAT),
                    'date_req2' => $range->to->value->format(self::DATE_FORMAT),
                    'VAL_NM_RQ' => $sourceId,
                ]),
            ),
        );
    }

    /**
     * @template T
     *
     * @param \Closure(): T $parse
     *
     * @return T
     */
    private function parse(\Closure $parse): mixed
    {
        try {
            return $parse();
        } catch (\InvalidArgumentException $error) {
            throw new SourceUnavailableException('CBR returned inconsistent rate data', previous: $error);
        }
    }

    /**
     * @param array<string, string> $query
     */
    private function request(string $path, array $query = []): string
    {
        try {
            $response = $this->httpClient->request(
                Request::METHOD_GET,
                $path,
                ['query' => $query],
            );

            $status = $response->getStatusCode();

            if ($status !== Response::HTTP_OK) {
                $headers = $response->getHeaders(false);
                $response->cancel();
                $error = new SourceUnavailableException(sprintf('CBR returned HTTP %d', $status));

                $this->logger->error('cbr.request.failed', [
                    'http_status' => $status,
                    'path'        => $path,
                    'query'       => $query,
                    'retry_after' => $headers['retry-after'][0] ?? null,
                    'exception'   => $error,
                ]);

                throw $error;
            }

            return $response->getContent();
        } catch (ExceptionInterface $error) {
            $this->logger->error('cbr.request.failed', [
                'path'      => $path,
                'query'     => $query,
                'exception' => $error,
            ]);

            throw new SourceUnavailableException('CBR is temporarily unavailable', previous: $error);
        }
    }
}
