<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Contract\CurrencyCatalogInterface;
use App\Import\Handler\PlanImportHandler;
use App\Import\Message\ImportCurrencyHistoryMessage;
use App\Import\Message\PlanImportMessage;
use App\Import\Model\ImportCurrency;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PlanImportHandlerTest extends TestCase
{
    /**
     * Для SAR создаются задания на оба ID ЦБ с одинаковым ISO-кодом
     */
    public function testSarImportIncludesBothCbrIds(): void
    {
        $messages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$messages): Envelope {
                self::assertInstanceOf(ImportCurrencyHistoryMessage::class, $message);
                $messages[] = $message;

                return new Envelope($message);
            });
        $handler = new PlanImportHandler(
            $this->catalog(),
            $bus,
            new NullLogger(),
        );
        $handler(new PlanImportMessage(
            ['SAR'],
            false,
            '2026-10-01',
            '2026-10-21',
            force: true,
        ));

        self::assertSame(
            ['R01580', 'R01580C'],
            array_map(static fn (ImportCurrencyHistoryMessage $message) => $message->sourceId, $messages),
        );

        foreach ($messages as $message) {
            self::assertSame('2026-10-21', $message->to);
            self::assertTrue($message->force);
        }
    }

    private function catalog(): CurrencyCatalogInterface
    {
        $catalog = $this->createMock(CurrencyCatalogInterface::class);
        $catalog->expects(self::once())->method('getCurrencies')->with(['SAR'])->willReturn([
            new ImportCurrency(
                'R01580',
                'SAR',
            ),
            new ImportCurrency(
                'R01580C',
                'SAR',
            ),
        ]);

        return $catalog;
    }
}
