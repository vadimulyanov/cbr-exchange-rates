<?php

declare(strict_types=1);

namespace App\Cbr\Persistence;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineTransaction
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->managerRegistry->getManager();

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('An ORM entity manager is required');
        }

        return $entityManager;
    }

    /**
     * @template T
     *
     * @param \Closure(EntityManagerInterface): T $operation
     *
     * @return T
     */
    public function run(\Closure $operation): mixed
    {
        $attempt = 0;

        do {
            ++$attempt;
            $entityManager = $this->getEntityManager();

            $entityManager->clear();

            try {
                $result = $entityManager->wrapInTransaction($operation);

                // Репозитории возвращают DTO - сущности не должны накапливаться между заданиями воркера
                $entityManager->clear();

                return $result;
            } catch (UniqueConstraintViolationException|RetryableException $error) {
                // После ошибки flush() менеджер закрыт, повтор заново читает данные в новой транзакции
                $this->managerRegistry->resetManager();
            } catch (\Throwable $error) {
                $this->managerRegistry->resetManager();

                throw $error;
            }
        } while ($attempt < self::MAX_ATTEMPTS);

        throw $error;
    }
}
