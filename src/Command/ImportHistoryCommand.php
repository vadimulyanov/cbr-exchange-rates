<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\Factory\ImportRequestFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'rates:import',
    description: 'Queue a historical import',
)]
final class ImportHistoryCommand extends Command
{
    public function __construct(
        private readonly ImportRequestFactory $importRequestFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'currency',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Currency to import (repeatable)',
        )
            ->addOption(
                'all-currencies',
                null,
                InputOption::VALUE_NONE,
                'Include all currency identifiers, including historical',
            )
            ->addOption(
                'previous-days',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Previous calendar days, excluding today (default: %d, maximum: %d)',
                    ImportRequestFactory::DEFAULT_DAYS,
                    ImportRequestFactory::MAX_DAYS,
                ),
            )
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Inclusive start date, YYYY-MM-DD')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Inclusive end date, YYYY-MM-DD')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Reload confirmed periods and update stored rates')
            ->setHelp(
                <<<'HELP'
                Examples:
                  rates:import --currency=USD --currency=EUR
                  rates:import --all-currencies --from=2026-10-01 --to=2026-10-21
                  rates:import --currency=USD --previous-days=365 --force
                HELP,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $message = $this->importRequestFactory->create(
                $input->getOption('currency'),
                $input->getOption('all-currencies'),
                $input->getOption('from'),
                $input->getOption('to'),
                $input->getOption('previous-days'),
                $input->getOption('force'),
            );
        } catch (\InvalidArgumentException $error) {
            $io->error(
                sprintf(
                    'Error: %s. Example: rates:import --currency=USD --currency=EUR',
                    $error->getMessage(),
                ),
            );

            return self::INVALID;
        }

        $this->messageBus->dispatch($message);

        $io->success(
            sprintf(
                'Import queued for %s through %s Processing continues in the workers',
                $message->from,
                $message->to,
            ),
        );

        return self::SUCCESS;
    }
}
