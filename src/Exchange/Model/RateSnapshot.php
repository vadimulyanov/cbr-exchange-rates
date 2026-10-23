<?php

declare(strict_types=1);

namespace App\Exchange\Model;

use App\Exchange\Exception\RateNotFoundException;

final readonly class RateSnapshot
{
    /**
     * @var list<Quote>
     */
    private array $quotes;

    /**
     * @param iterable<Quote> $quotes
     */
    public function __construct(
        public CalendarDate $date,
        iterable $quotes,
    ) {
        $items = [];

        foreach ($quotes as $quote) {
            if ((string) $quote->date !== (string) $date) {
                throw new \InvalidArgumentException('All snapshot quotes must have the same effective date');
            }

            foreach ($items as $existing) {
                if ($existing->currency->value === $quote->currency->value) {
                    throw new \InvalidArgumentException('Duplicate currency in a rate snapshot');
                }
            }
            $items[] = $quote;
        }

        if (empty($items)) {
            throw new RateNotFoundException('No rates are available on the requested date');
        }
        $this->quotes = $items;
    }

    public function quote(CurrencyCode $currency): Quote
    {
        foreach ($this->quotes as $quote) {
            if ($quote->currency->value === $currency->value) {
                return $quote;
            }
        }

        throw new RateNotFoundException(sprintf('No %s rate is available on %s', $currency, $this->date));
    }
}
