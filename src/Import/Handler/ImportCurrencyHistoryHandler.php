<?php

declare(strict_types=1);

namespace App\Import\Handler;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use App\Import\Contract\HistoryImporterInterface;
use App\Import\Message\ImportCurrencyHistoryMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ImportCurrencyHistoryHandler
{
    public function __construct(
        private HistoryImporterInterface $historyImporter,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportCurrencyHistoryMessage $message): void
    {
        try {
            $range = new DateRange(
                new CalendarDate($message->from),
                new CalendarDate($message->to),
            );

            $count = $this->historyImporter->import(
                $message->sourceId,
                $range,
                includePreviousQuote: true,
                force: $message->force,
            );
        } catch (\InvalidArgumentException $error) {
            throw new UnrecoverableMessageHandlingException($error->getMessage(), previous: $error);
        }

        $this->logger->info('import.currency.completed', [
            'source_id' => $message->sourceId,
            'from'      => $message->from,
            'to'        => $message->to,
            'quotes'    => $count,
            'force'     => $message->force,
        ]);
    }
}
