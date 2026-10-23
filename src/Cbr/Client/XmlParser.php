<?php

declare(strict_types=1);

namespace App\Cbr\Client;

use App\Cbr\Model\CurrencyRecord;
use App\Cbr\Model\HistoryRecord;
use App\Exchange\Exception\SourceUnavailableException;
use App\Exchange\Model\CalendarDate;

final class XmlParser
{
    /**
     * @return list<CurrencyRecord>
     */
    public function getCurrencies(string $xml): array
    {
        $root = $this->document($xml, 'Valuta');
        $currencies = [];

        foreach ($root->getElementsByTagName('Item') as $item) {
            $currencies[] = new CurrencyRecord(
                $item->getAttribute('ID'),
                $this->text($item, 'Name'),
                $this->text($item, 'ISO_Char_Code') ?: null,
                $this->text($item, 'ISO_Num_Code') ?: null,
                $this->text($item, 'ParentCode') ?: null,
            );
        }

        if (empty($currencies)) {
            throw new SourceUnavailableException('CBR returned an empty currency catalog');
        }

        return $currencies;
    }

    /**
     * @return list<HistoryRecord>
     */
    public function history(string $xml): array
    {
        $root = $this->document($xml, 'ValCurs');
        $rates = [];

        foreach ($root->getElementsByTagName('Record') as $item) {
            $id = $item->getAttribute('Id');

            if (!preg_match('/^R[0-9A-Za-z]+$/D', $id)) {
                throw new SourceUnavailableException('CBR history contains an unknown currency identifier');
            }
            $rates[] = $this->rate($item, $id, $this->date($item->getAttribute('Date')));
        }

        return $rates;
    }

    private function document(string $xml, string $rootName): \DOMElement
    {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new SourceUnavailableException('DTD and entities are not allowed in CBR responses');
        }
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();

            if (
                !$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)
                || $document->doctype !== null
                || $document->documentElement?->tagName !== $rootName
            ) {
                throw new SourceUnavailableException('CBR returned an invalid XML document');
            }

            return $document->documentElement;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function rate(\DOMElement $item, string $sourceId, CalendarDate $date): HistoryRecord
    {
        $nominal = $this->text($item, 'Nominal');

        if (!preg_match('/^[1-9]\d{0,8}$/D', $nominal)) {
            throw new SourceUnavailableException('CBR returned an invalid nominal');
        }

        return new HistoryRecord(
            $sourceId,
            $date,
            str_replace(',', '.', $this->text($item, 'Value')),
            (int) $nominal,
        );
    }

    private function date(string $value): CalendarDate
    {
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value);

        if (!$date || $date->format('d.m.Y') !== $value) {
            throw new SourceUnavailableException('CBR returned an invalid effective date');
        }

        return new CalendarDate($date);
    }

    private function text(\DOMElement $element, string $name): string
    {
        return trim($element->getElementsByTagName($name)->item(0)->textContent ?? '');
    }
}
