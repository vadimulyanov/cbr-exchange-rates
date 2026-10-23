<?php

declare(strict_types=1);

namespace App\Import\Handler;

use App\Import\Contract\CurrencyCatalogInterface;
use App\Import\Message\ImportCurrencyHistoryMessage;
use App\Import\Message\PlanImportMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class PlanImportHandler
{
    public function __construct(
        private CurrencyCatalogInterface $currencyCatalog,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PlanImportMessage $message): void
    {
        try {
            $currencies = $this->currencyCatalog->getCurrencies(
                $message->allCurrencies ? null : $message->currencies,
            );
        } catch (\InvalidArgumentException $error) {
            throw new UnrecoverableMessageHandlingException($error->getMessage(), previous: $error);
        }

        $unknown = array_diff($message->currencies, array_column($currencies, 'code'));

        if (!empty($unknown)) {
            throw new UnrecoverableMessageHandlingException(
                sprintf(
                    'Unknown currency code: %s',
                    implode(', ', $unknown),
                ),
            );
        }

        foreach ($currencies as $currency) {
            $this->messageBus->dispatch(
                new ImportCurrencyHistoryMessage(
                    $currency->sourceId,
                    $message->from,
                    $message->to,
                    $message->force,
                ),
            );
        }
        $this->logger->info('import.history.planned', [
            'currencies' => count($currencies),
            'from'       => $message->from,
            'to'         => $message->to,
            'force'      => $message->force,
        ]);
    }
}
