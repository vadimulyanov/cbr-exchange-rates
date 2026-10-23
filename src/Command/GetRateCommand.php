<?php

declare(strict_types=1);

namespace App\Command;

use App\Exchange\Dto\RateRequest;
use App\Exchange\Model\CurrencyCode;
use App\Exchange\Service\ExchangeRateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rates:get', description: 'Get a CBR rate and its change from the preceding quotation date')]
final class GetRateCommand extends Command
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date in YYYY-MM-DD format')
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Three-letter currency code')
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Base currency code', CurrencyCode::RUR)
            ->setHelp('Example: php bin/console rates:get --date=2026-10-22 --currency=USD --base=EUR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->exchangeRateService->getRate(new RateRequest(
                $input->getOption('date') ?? '',
                $input->getOption('currency') ?? '',
                $input->getOption('base'),
            ));
        } catch (\InvalidArgumentException $error) {
            $io->error($error->getMessage());

            return self::INVALID;
        }

        $io->table(['Field', 'Value'], [
            ['Requested date', $result->requestedDate],
            ['Effective date', $result->effectiveDate],
            ['Previous date', $result->previousDate],
            ['Currency', $result->currency],
            ['Base', $result->base],
            ['Rate', $result->rate],
            ['Previous rate', $result->previousRate],
            ['Change', $result->change],
            ['Change (%)', $result->changePercent],
        ]);

        return self::SUCCESS;
    }
}
