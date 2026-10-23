<?php

declare(strict_types=1);

namespace App\Cbr\Entity;

use App\Cbr\Model\CurrencyRecord;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cbr_currency')]
#[ORM\Index(name: 'cbr_currency_char_code_idx', columns: ['char_code'])]
class CbrCurrency
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $id,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(length: 3, nullable: true)]
        private ?string $charCode,
        #[ORM\Column(length: 3, nullable: true)]
        private ?string $numCode,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $parentCode,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $catalogFetchedAt = null,
    ) {
    }

    public static function fromRecord(
        CurrencyRecord $currency,
        ?\DateTimeImmutable $catalogFetchedAt = null,
    ): self {
        return new self(
            $currency->id,
            $currency->name,
            $currency->charCode,
            $currency->numCode,
            $currency->parentCode,
            $catalogFetchedAt,
        );
    }

    public function update(
        CurrencyRecord $currency,
        ?\DateTimeImmutable $catalogFetchedAt = null,
    ): void {
        $this->name = $currency->name;
        $this->charCode = $currency->charCode;
        $this->numCode = $currency->numCode;
        $this->parentCode = $currency->parentCode ?? $this->parentCode;
        $this->catalogFetchedAt = $catalogFetchedAt ?? $this->catalogFetchedAt;
    }

    public function record(): CurrencyRecord
    {
        return new CurrencyRecord(
            $this->id,
            $this->name,
            $this->charCode,
            $this->numCode,
            $this->parentCode,
        );
    }

    public function isCatalogFresh(\DateTimeImmutable $since): bool
    {
        return $this->catalogFetchedAt !== null && $this->catalogFetchedAt >= $since;
    }
}
