<?php

namespace App\Command;

use App\Repository\ReportRunRepository;
use App\Service\Report\ReportVehicleProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:vehicles:queue', description: 'Queue vehicle detail requests for a report run')]
class QueueVehicleDetailsCommand extends Command
{
    public function __construct(
        private readonly ReportRunRepository $runRepository,
        private readonly ReportVehicleProcessor $vehicleProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Identifier of the report run to queue vehicles for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $runId = (int) $input->getArgument('run-id');

        $run = $this->runRepository->find($runId);

        if (!$run) {
            $io->error(sprintf('Report run %d not found.', $runId));

            return Command::FAILURE;
        }

        $queued = $this->vehicleProcessor->queueVehiclesForRun($run);

        if ($queued === 0) {
            $io->warning('No vehicles were queued. Ensure the run has successful query responses with vehicle data.');
        } else {
            $io->success(sprintf('Queued %d vehicles for report run %d.', $queued, $runId));
        }

        return Command::SUCCESS;
    }
}
