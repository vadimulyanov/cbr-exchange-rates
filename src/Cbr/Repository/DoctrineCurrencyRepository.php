<?php

declare(strict_types=1);

namespace App\Cbr\Repository;

use App\Cbr\Entity\CbrCurrency;
use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Persistence\DoctrineTransaction;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class DoctrineCurrencyRepository implements CurrencyRepositoryInterface
{
    public function __construct(private DoctrineTransaction $doctrineTransaction)
    {
    }

    public function save(CurrencyRecord $currency, ?\DateTimeImmutable $catalogFetchedAt = null): void
    {
        $this->doctrineTransaction->run(
            fn (EntityManagerInterface $entityManager) => $this->saveCurrency(
                $entityManager,
                $currency,
                $catalogFetchedAt,
            ),
        );
    }

    public function findAll(): array
    {
        return $this->findRecords();
    }

    public function findByCodes(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        return $this->findRecords(codes: $codes);
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->findRecords(ids: $ids);
    }

    public function saveCatalog(iterable $currencies, \DateTimeImmutable $fetchedAt): void
    {
        $ordered = iterator_to_array($currencies, false);
        usort(
            $ordered,
            static fn (CurrencyRecord $first, CurrencyRecord $second) => $first->id <=> $second->id,
        );

        $this->doctrineTransaction->run(
            function (EntityManagerInterface $entityManager) use ($ordered, $fetchedAt): void {
                foreach ($ordered as $currency) {
                    $this->saveCurrency($entityManager, $currency, $fetchedAt);
                }
            },
        );
    }

    public function isCatalogFresh(\DateTimeImmutable $since): bool
    {
        /** @var CbrCurrency|null $oldest */
        $oldest = $this->doctrineTransaction->getEntityManager()->createQueryBuilder()
            ->select('currency')
            ->addSelect('CASE WHEN currency.catalogFetchedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN freshness')
            ->from(CbrCurrency::class, 'currency')
            ->orderBy('freshness', \SortDirection::Ascending)
            ->addOrderBy('currency.catalogFetchedAt', \SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        // Сначала проверяем записи без даты загрузки - они еще не подтверждены полным справочником
        return $oldest?->isCatalogFresh($since) ?? false;
    }

    /**
     * @param list<string>|null $codes
     * @param list<string>|null $ids
     *
     * @return array<string, CurrencyRecord>
     */
    private function findRecords(?array $codes = null, ?array $ids = null): array
    {
        $queryBuilder = $this->doctrineTransaction->getEntityManager()->createQueryBuilder()
            ->select('currency')
            ->from(CbrCurrency::class, 'currency')
            ->orderBy('currency.id', \SortDirection::Ascending);

        if ($codes !== null) {
            $queryBuilder
                ->where('currency.charCode IN (:codes)')
                ->setParameter('codes', $codes);
        }

        if ($ids !== null) {
            $queryBuilder
                ->andWhere('currency.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        $currencies = $queryBuilder->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        $records = [];

        /** @var CbrCurrency $currency */
        foreach ($currencies as $currency) {
            $record = $currency->record();
            $records[$record->id] = $record;
        }

        return $records;
    }

    private function saveCurrency(
        EntityManagerInterface $entityManager,
        CurrencyRecord $currency,
        ?\DateTimeImmutable $catalogFetchedAt,
    ): void {
        $entity = $entityManager->find(CbrCurrency::class, $currency->id, LockMode::PESSIMISTIC_WRITE);

        if ($entity === null) {
            $entityManager->persist(CbrCurrency::fromRecord($currency, $catalogFetchedAt));
        } else {
            $entity->update($currency, $catalogFetchedAt);
        }
    }
}
