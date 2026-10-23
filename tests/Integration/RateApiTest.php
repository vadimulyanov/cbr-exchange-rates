<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RateApiTest extends WebTestCase
{
    /**
     * Если за неделю нет котировок, поиск один раз расширяется до 180 дней
     */
    public function testSearchExpandsWhenInitialRangeIsEmpty(): void
    {
        $browser = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        self::assertStringEndsWith('_test', $connection->fetchOne('SELECT current_database()'));

        $connection->executeStatement('TRUNCATE cbr_rate, cbr_currency RESTART IDENTITY CASCADE');
        $olderHistory = str_replace(
            ['16.10.2026', '17.10.2026'],
            ['26.09.2026', '27.09.2026'],
            $this->fixture('history'),
        );
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse('<ValCurs ID="R01235"/>'),
            new MockResponse($olderHistory),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);

        $browser->request('GET', '/api/v1/rates?date=2026-10-18&currency=USD');

        self::assertResponseIsSuccessful();

        $result = json_decode((string) $browser->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('90.000000000000', $result['rate']);
        self::assertSame('2026-09-27', $result['effectiveDate']);
        self::assertSame('2026-09-26', $result['previousDate']);
        self::assertSame(3, $httpClient->getRequestsCount());
    }

    /**
     * API и CLI возвращают одинаковый курс без повторного запроса к ЦБ
     */
    public function testApiAndCliReturnSameCachedRate(): void
    {
        $browser = self::createClient();
        $connection = self::getContainer()->get(Connection::class);
        self::assertStringEndsWith('_test', $connection->fetchOne('SELECT current_database()'));

        $connection->executeStatement('TRUNCATE cbr_rate, cbr_currency RESTART IDENTITY CASCADE');
        $euroHistory = str_replace(
            '</ValCurs>',
            '<Record Date="17.10.2026" Id="R01239"><Nominal>1</Nominal><Value>100,0000</Value></Record></ValCurs>',
            $this->fixture('history-eur'),
        );
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture('catalog')),
            new MockResponse($this->fixture('history')),
            new MockResponse($euroHistory),
        ]);
        self::getContainer()->set('cbr.client', $httpClient);

        $browser->request('GET', '/api/v1/rates?date=2026-10-17&currency=USD&base=EUR');
        self::assertResponseIsSuccessful();

        $content = $browser->getResponse()->getContent();
        self::assertIsString($content);

        $result = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('0.900000000000', $result['rate']);
        self::assertSame('0.100000000000', $result['change']);
        self::assertSame('12.50000000', $result['changePercent']);
        self::assertSame('2026-10-16', $result['previousDate']);
        self::assertSame('2026-10-17', $result['effectiveDate']);
        self::assertSame(3, $httpClient->getRequestsCount());

        self::assertNotNull(self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find('rates:get'));
        $tester->execute(['--date' => '2026-10-17', '--currency' => 'USD', '--base' => 'EUR']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString($result['rate'], $tester->getDisplay());
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM cbr_rate'));
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/Fixtures/' . $name . '.xml');

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read fixture: %s', $name));
        }

        return $contents;
    }
}
