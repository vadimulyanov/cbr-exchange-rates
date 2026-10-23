<?php

declare(strict_types=1);

namespace App\Cbr\Service;

use App\Exchange\Model\CurrencyCode;
use App\Import\Contract\CurrencyCatalogInterface;
use App\Import\Model\ImportCurrency;

final readonly class CbrImportCurrencyCatalog implements CurrencyCatalogInterface
{
    public function __construct(private CbrCurrencyCatalog $currencyCatalog)
    {
    }

    /**
     * @param list<string>|null $codes
     *
     * @return list<ImportCurrency>
     */
    public function getCurrencies(?array $codes = null): array
    {
        if ($codes !== null && in_array(CurrencyCode::RUB, $codes, true)) {
            throw new \InvalidArgumentException('RUR/RUB is the reference currency and has no history to import');
        }

        $currencies = [];

        foreach ($this->currencyCatalog->getRecords($codes) as $record) {
            $currencies[] = new ImportCurrency($record->id, $record->charCode);
        }

        return $currencies;
    }
}
