<?php

declare(strict_types=1);

namespace App\Command;

use App\Cbr\Repository\RateRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'rates:clear', description: 'Delete all stored rates')]
final class ClearRatesCommand extends Command
{
    public function __construct(private readonly RateRepositoryInterface $rateRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->rateRepository->deleteAll();

        (new SymfonyStyle($input, $output))->success(sprintf('Deleted rates: %d', $count));

        return self::SUCCESS;
    }
}
