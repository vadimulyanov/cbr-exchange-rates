<?php

declare(strict_types=1);

namespace App\Cbr\Repository;

use App\Cbr\Entity\CbrCurrency;
use App\Cbr\Entity\CbrHistoryRange;
use App\Cbr\Entity\CbrRate;
use App\Cbr\Model\CurrencyHistory;
use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Model\RateRecord;
use App\Cbr\Persistence\DoctrineTransaction;
use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\DateRange;
use App\Exchange\Model\Quote;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class DoctrineRateRepository implements RateRepositoryInterface
{
    public function __construct(private DoctrineTransaction $doctrineTransaction)
    {
    }

    public function findLatest(CurrencyRecord $currency, CalendarDate $date, CalendarDate $notBefore): ?Quote
    {
        $code = $currency->charCode ?? throw new \LogicException('A public quote requires an ISO currency code');
        $rate = $this->doctrineTransaction->getEntityManager()->createQueryBuilder()
            ->select('rate')
            ->from(CbrRate::class, 'rate')
            ->where('rate.currency = :currency')
            ->andWhere('rate.effectiveDate <= :date')
            ->andWhere('rate.effectiveDate >= :notBefore')
            ->setParameter('currency', $currency->id)
            ->setParameter('date', $date->value, Types::DATE_IMMUTABLE)
            ->setParameter('notBefore', $notBefore->value, Types::DATE_IMMUTABLE)
            ->orderBy('rate.effectiveDate', \SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $rate?->getQuote(new CurrencyCode($code));
    }

    public function saveHistory(CurrencyHistory $history): int
    {
        $ordered = $history->rates;
        usort(
            $ordered,
            static fn (RateRecord $first, RateRecord $second) => [
                $first->currency->id,
                (string) $first->date,
            ] <=> [
                $second->currency->id,
                (string) $second->date,
            ],
        );
        $currencies = [$history->currency->id => $history->currency];

        foreach ($ordered as $rate) {
            $currencies[$rate->currency->id] = $rate->currency;
        }

        // Одинаковый порядок блокировок снижает риск взаимных блокировок между импортами
        ksort($currencies);

        return $this->doctrineTransaction->run(
            function (EntityManagerInterface $entityManager) use (
                $history,
                $ordered,
                $currencies,
            ): int {
                $currencyEntities = [];
                $rateEntities = [];

                foreach ($currencies as $id => $currency) {
                    $currencyEntities[$id] = $this->getOrCreateCurrency($entityManager, $currency);
                }

                $this->removeMissingRates($entityManager, $history);

                foreach ($ordered as $rateRecord) {
                    $key = sprintf('%s:%s', $rateRecord->currency->id, $rateRecord->date);
                    $rateEntity = $rateEntities[$key] ?? $this->findRate($entityManager, $rateRecord);

                    if ($rateEntity === null) {
                        $rateEntity = new CbrRate(
                            $currencyEntities[$rateRecord->currency->id],
                            $rateRecord->date->value,
                            $rateRecord->value,
                            $rateRecord->nominal,
                        );
                        $entityManager->persist($rateEntity);
                    } else {
                        $rateEntity->update($rateRecord->value, $rateRecord->nominal);
                    }

                    $rateEntities[$key] = $rateEntity;
                }

                $this->mergeHistoryRange($entityManager, $currencyEntities[$history->currency->id], $history->range);

                return count($ordered);
            },
        );
    }

    public function deleteAll(): int
    {
        return $this->doctrineTransaction->run(
            static function (EntityManagerInterface $entityManager): int {
                // Очистка и импорт используют одинаковый порядок блокировок валют
                $entityManager->createQueryBuilder()
                    ->select('currency')
                    ->from(CbrCurrency::class, 'currency')
                    ->orderBy('currency.id', \SortDirection::Ascending)
                    ->getQuery()
                    ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                    ->getResult();
                $entityManager->createQueryBuilder()
                    ->delete(CbrHistoryRange::class, 'historyRange')
                    ->getQuery()
                    ->execute();

                return (int) $entityManager->createQueryBuilder()
                    ->delete(CbrRate::class, 'rate')
                    ->getQuery()
                    ->execute();
            },
        );
    }

    public function hasHistory(CurrencyRecord $currency, DateRange $range): bool
    {
        return $this->doctrineTransaction->getEntityManager()->createQueryBuilder()
            ->select('historyRange.id')
            ->from(CbrHistoryRange::class, 'historyRange')
            ->where('historyRange.currency = :currency')
            ->andWhere('historyRange.dateFrom <= :from')
            ->andWhere('historyRange.dateTo >= :to')
            ->setParameter('currency', $currency->id)
            ->setParameter('from', $range->from->value, Types::DATE_IMMUTABLE)
            ->setParameter('to', $range->to->value, Types::DATE_IMMUTABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    private function mergeHistoryRange(
        EntityManagerInterface $entityManager,
        CbrCurrency $currency,
        DateRange $range,
    ): void {
        $ranges = $entityManager->createQueryBuilder()
            ->select('historyRange')
            ->from(CbrHistoryRange::class, 'historyRange')
            ->where('historyRange.currency = :currency')
            ->andWhere('historyRange.dateFrom <= :nextDay')
            ->andWhere('historyRange.dateTo >= :previousDay')
            ->setParameter('currency', $currency)
            ->setParameter('nextDay', $range->to->value->modify('+1 day'), Types::DATE_IMMUTABLE)
            ->setParameter('previousDay', $range->from->value->modify('-1 day'), Types::DATE_IMMUTABLE)
            ->orderBy('historyRange.dateFrom', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
        $merged = null;

        foreach ($ranges as $existing) {
            if ($merged === null) {
                $merged = $existing;
                $merged->merge($range);
            } else {
                $merged->merge($existing->getRange());
                $entityManager->remove($existing);
            }
        }

        if ($merged === null) {
            $entityManager->persist(new CbrHistoryRange(
                $currency,
                $range->from->value,
                $range->to->value,
            ));
        }
    }

    private function getOrCreateCurrency(
        EntityManagerInterface $entityManager,
        CurrencyRecord $currencyRecord,
    ): CbrCurrency {
        $currencyEntity = $entityManager->find(CbrCurrency::class, $currencyRecord->id, LockMode::PESSIMISTIC_WRITE);

        if ($currencyEntity === null) {
            $currencyEntity = CbrCurrency::fromRecord($currencyRecord);
            $entityManager->persist($currencyEntity);
        } else {
            $currencyEntity->update($currencyRecord);
        }

        return $currencyEntity;
    }

    private function findRate(EntityManagerInterface $entityManager, RateRecord $rateRecord): ?CbrRate
    {
        return $entityManager->createQueryBuilder()
            ->select('rate')
            ->from(CbrRate::class, 'rate')
            ->where('rate.currency = :currency')
            ->andWhere('rate.effectiveDate = :date')
            ->setParameter('currency', $rateRecord->currency->id)
            ->setParameter('date', $rateRecord->date->value, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    private function removeMissingRates(EntityManagerInterface $entityManager, CurrencyHistory $history): void
    {
        $dates = [];

        foreach ($history->rates as $rate) {
            if ($rate->currency->id === $history->currency->id) {
                $dates[] = (string) $rate->date;
            }
        }

        // При обновлении диапазона удаленные источником котировки не должны оставаться в кеше
        $queryBuilder = $entityManager->createQueryBuilder()
            ->delete(CbrRate::class, 'rate')
            ->where('rate.currency = :currency')
            ->andWhere('rate.effectiveDate >= :from')
            ->andWhere('rate.effectiveDate <= :to')
            ->setParameter('currency', $history->currency->id)
            ->setParameter('from', $history->range->from->value, Types::DATE_IMMUTABLE)
            ->setParameter('to', $history->range->to->value, Types::DATE_IMMUTABLE);

        if (!empty($dates)) {
            $queryBuilder->andWhere('rate.effectiveDate NOT IN (:dates)')->setParameter('dates', $dates);
        }

        $queryBuilder->getQuery()->execute();
    }
}
