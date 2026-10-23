<?php

declare(strict_types=1);

namespace App\Cbr\Repository;

use App\Cbr\Model\CurrencyRecord;

interface CurrencyRepositoryInterface
{
    public function save(CurrencyRecord $currency, ?\DateTimeImmutable $catalogFetchedAt = null): void;

    /**
     * @param iterable<CurrencyRecord> $currencies
     */
    public function saveCatalog(iterable $currencies, \DateTimeImmutable $fetchedAt): void;

    /**
     * @return array<string, CurrencyRecord>
     */
    public function findAll(): array;

    /**
     * @param list<string> $codes
     *
     * @return array<string, CurrencyRecord>
     */
    public function findByCodes(array $codes): array;

    /**
     * @param list<string> $ids
     *
     * @return array<string, CurrencyRecord>
     */
    public function findByIds(array $ids): array;

    public function isCatalogFresh(\DateTimeImmutable $since): bool;
}
