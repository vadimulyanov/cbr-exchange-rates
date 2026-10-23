<?php

declare(strict_types=1);

namespace App\Import\Contract;

use App\Import\Model\ImportCurrency;

interface CurrencyCatalogInterface
{
    /**
     * @param list<string>|null $codes null - весь справочник
     *
     * @return list<ImportCurrency> Валюты с уникальными ID источника
     */
    public function getCurrencies(?array $codes = null): array;
}
