<?php

declare(strict_types=1);

namespace App\Cbr\Entity;

use App\Exchange\Model\CalendarDate;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Model\Quote;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cbr_rate')]
#[ORM\UniqueConstraint(name: 'cbr_rate_currency_date_uniq', columns: ['currency_id', 'effective_date'])]
class CbrRate
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
        private \DateTimeImmutable $effectiveDate,
        #[ORM\Column(type: 'decimal', precision: 30, scale: 12)]
        private string $value,
        #[ORM\Column]
        private int $nominal,
    ) {
    }

    public function update(string $value, int $nominal): void
    {
        $this->value = $value;
        $this->nominal = $nominal;
    }

    public function getQuote(CurrencyCode $currency): Quote
    {
        return new Quote(
            $currency,
            new CalendarDate($this->effectiveDate),
            $this->value,
            $this->nominal,
        );
    }
}
