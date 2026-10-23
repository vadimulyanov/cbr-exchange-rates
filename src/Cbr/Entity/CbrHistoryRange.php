<?php

declare(strict_types=1);

namespace App\Cbr\Entity;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\DateRange;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cbr_history_range')]
#[ORM\UniqueConstraint(name: 'cbr_history_range_currency_from_uniq', columns: ['currency_id', 'date_from'])]
class CbrHistoryRange
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CbrCurrency::class)]
        #[ORM\JoinColumn(nullable: false)]
        private CbrCurrency $currency,
        #[ORM\Column(type: 'date_immutable')]
        private \DateTimeImmutable $dateFrom,
        #[ORM\Column(type: 'date_immutable')]
        private \DateTimeImmutable $dateTo,
    ) {
    }

    public function merge(DateRange $range): void
    {
        $this->dateFrom = min($this->dateFrom, $range->from->value);
        $this->dateTo = max($this->dateTo, $range->to->value);
    }

    public function getRange(): DateRange
    {
        return new DateRange(
            new CalendarDate($this->dateFrom),
            new CalendarDate($this->dateTo),
        );
    }
}
