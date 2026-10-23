<?php

declare(strict_types=1);

namespace App\Cbr\Service;

use App\Cbr\Client\CbrClient;
use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Repository\CurrencyRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class CbrCurrencyCatalog
{
    public function __construct(
        private CbrClient $cbrClient,
        private CurrencyRepositoryInterface $currencyRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string>|null $codes
     *
     * @return array<string, CurrencyRecord>
     */
    public function getRecords(?array $codes = null): array
    {
        if ($codes === []) {
            return [];
        }

        $this->refreshIfStale();

        return $codes === null
            ? $this->currencyRepository->findAll()
            : $this->currencyRepository->findByCodes($codes);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, CurrencyRecord>
     */
    public function getRecordsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $this->refreshIfStale();

        return $this->currencyRepository->findByIds($ids);
    }

    private function refreshIfStale(): void
    {
        if (!$this->currencyRepository->isCatalogFresh($this->clock->now()->modify('-1 day'))) {
            $this->currencyRepository->saveCatalog(
                $this->cbrClient->getCurrencies(),
                $this->clock->now(),
            );
        }
    }
}
