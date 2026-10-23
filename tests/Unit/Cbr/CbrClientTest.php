<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cbr;

use App\Cbr\Client\CbrClient;
use App\Cbr\Client\XmlParser;
use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class CbrClientTest extends TestCase
{
    /**
     * При ошибке HTTP сохраняется контекст запроса и выбрасывается ошибка источника
     */
    #[DataProvider('httpErrors')]
    public function testHttpErrorLogsRequestContext(int $status): void
    {
        $response = new MockResponse('', [
            'http_code' => $status,
        ]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'cbr.request.failed',
            self::callback(static function (array $context) use ($status): bool {
                self::assertSame($status, $context['http_status']);
                self::assertSame([
                    'date_req1' => '21/10/2026',
                    'date_req2' => '22/10/2026',
                    'VAL_NM_RQ' => 'R01235',
                ], $context['query']);

                return true;
            }),
        );
        $client = new CbrClient(
            new MockHttpClient($response),
            new XmlParser(),
            $logger,
        );
        $range = new DateRange(
            new CalendarDate('2026-10-21'),
            new CalendarDate('2026-10-22'),
        );
        $this->expectException(SourceUnavailableException::class);
        $this->expectExceptionMessage(sprintf('CBR returned HTTP %d', $status));

        $client->history('R01235', $range);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function httpErrors(): iterable
    {
        yield 'rate limit' => [Response::HTTP_TOO_MANY_REQUESTS];
        yield 'service unavailable' => [Response::HTTP_SERVICE_UNAVAILABLE];
    }
}
