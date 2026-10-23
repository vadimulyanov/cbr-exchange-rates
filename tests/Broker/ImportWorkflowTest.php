<?php

declare(strict_types=1);

namespace App\Tests\Broker;

use App\Exchange\Dto\RateRequest;
use App\Exchange\Service\ExchangeRateService;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

#[Group('rabbitmq')]
final class ImportWorkflowTest extends DatabaseTestCase
{
    /**
     * Команда проходит обе очереди, ошибка ЦБ повторяется, результат доступен из кеша
     */
    #[DataProvider('httpErrors')]
    public function testImportThroughRabbitMq(int $status): void
    {
        $dsn = getenv('RABBITMQ_TEST_DSN');
        self::assertIsString($dsn, 'Set RABBITMQ_TEST_DSN to run the broker test');
        $container = self::getContainer();

        $prefix = 'cbr.test.' . bin2hex(random_bytes(8));
        $connections = [];
        $transports = [];

        try {
            foreach (['planning', 'imports', 'failed'] as $name) {
                $queue = $prefix . '.' . $name;
                $connection = Connection::fromDsn($dsn, [
                    'exchange'        => ['name' => $queue],
                    'queues'          => [$queue => null],
                    'confirm_timeout' => 5,
                ]);
                $transport = new AmqpTransport($connection, Serializer::create());
                $transport->setup();
                $connections[$queue] = $connection;
                $transports[$name] = $transport;
                $container->set('messenger.transport.' . $name, $transport);
            }

            // Ускоряем только ожидание повтора, обработчики и события Messenger остаются настоящими
            $container->set(
                'messenger.retry.multiplier_retry_strategy.imports',
                new MultiplierRetryStrategy(3, 50, 2, 1000, 0),
            );

            $responses = [
                new MockResponse($this->fixture('catalog')),
                new MockResponse('', ['http_code' => $status]),
                new MockResponse($this->fixture('history')),
            ];

            // Messenger сбрасывает счетчик MockHttpClient между сообщениями, считаем запросы отдельно
            $requests = 0;
            $httpClient = new MockHttpClient(static function () use ($responses, &$requests): MockResponse {
                return $responses[$requests++] ?? throw new \LogicException('Unexpected CBR request');
            });
            $container->set('cbr.client', $httpClient);

            $retries = 0;
            $container->get(EventDispatcherInterface::class)->addListener(
                WorkerMessageRetriedEvent::class,
                static function () use (&$retries): void {
                    ++$retries;
                },
            );

            $this->assertCommandIsSuccessful(self::runCommand('rates:import', [
                '--currency' => ['USD'],
                '--from'     => '2026-10-18',
                '--to'       => '2026-10-19',
            ], interactive: false));
            self::assertSame(1, $transports['planning']->getMessageCount());

            $this->assertCommandIsSuccessful(self::runCommand('messenger:consume', [
                'receivers'    => ['planning', 'imports'],
                '--limit'      => 3,
                '--time-limit' => 5,
            ], interactive: false));

            self::assertSame(1, $retries);
            self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cbr_rate'));

            $result = $container->get(ExchangeRateService::class)->getRate(new RateRequest('2026-10-18', 'USD'));

            self::assertSame('90.000000000000', $result->rate);
            self::assertSame('2026-10-17', $result->effectiveDate);
            self::assertSame(3, $requests);

            foreach ($transports as $transport) {
                self::assertSame(0, $transport->getMessageCount());
            }
        } finally {
            foreach ($connections as $queue => $connection) {
                $connection->queue($queue)->delete();
                $connection->exchange()->delete();
                $connection->clear();
            }
        }
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
