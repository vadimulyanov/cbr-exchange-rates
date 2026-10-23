<?php

declare(strict_types=1);

namespace App\Import\Logging;

use App\Import\Message\ImportCurrencyHistoryMessage;
use App\Import\Message\PlanImportMessage;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\Messenger\Exception\EnvelopeAwareExceptionInterface;

#[AsMonologProcessor(channel: 'messenger')]
final class ImportFailureProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;

        if (!$exception instanceof EnvelopeAwareExceptionInterface) {
            return $record;
        }

        $message = $exception->getEnvelope()?->getMessage();

        // Дополняем штатную запись Messenger, сохраняя исключение и сведения о повторе
        $context = match (true) {
            $message instanceof ImportCurrencyHistoryMessage => [
                'source_id' => $message->sourceId,
                'from'      => $message->from,
                'to'        => $message->to,
            ],
            $message instanceof PlanImportMessage => [
                'currencies'     => $message->currencies,
                'all_currencies' => $message->allCurrencies,
                'from'           => $message->from,
                'to'             => $message->to,
            ],
            default => [],
        };

        return $record->with(context: $record->context + $context);
    }
}
