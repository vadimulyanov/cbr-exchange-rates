<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exchange;

use App\Exchange\Contract\RateProviderInterface;
use App\Exchange\Dto\RateRequest;
use App\Exchange\Service\CrossRateCalculator;
use App\Exchange\Service\ExchangeRateService;
use App\Exchange\Service\PreviousRateResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ExchangeRateServiceTest extends TestCase
{
    /**
     * Будущая дата отклоняется до обращения к источнику курса
     */
    public function testRejectsFutureDate(): void
    {
        $rateProvider = $this->createMock(RateProviderInterface::class);
        $rateProvider->expects(self::never())->method('getRatesOn');

        $service = new ExchangeRateService(
            $rateProvider,
            new PreviousRateResolver($rateProvider),
            new CrossRateCalculator(),
            new MockClock('2026-10-23 12:00:00', 'Europe/Moscow'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Future dates are not supported');

        $service->getRate(new RateRequest('2026-10-24', 'RUR'));
    }
}
