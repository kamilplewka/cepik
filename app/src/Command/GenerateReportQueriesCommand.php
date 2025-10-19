<?php

namespace App\Command;

use App\Repository\ReportRunRepository;
use App\Service\Report\ReportQueryGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:generate-queries', description: 'Generate query workload for a given report run')]
class GenerateReportQueriesCommand extends Command
{
    public function __construct(
        private readonly ReportRunRepository $runRepository,
        private readonly ReportQueryGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Identifier of the report run');
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

        $payload = $run->getInputPayload();
        $regions = \is_array($payload['regions'] ?? null) ? $payload['regions'] : [];
        $years = \is_array($payload['years'] ?? null) ? $payload['years'] : [];
        $windows = \is_array($payload['date_windows'] ?? null) ? $payload['date_windows'] : [];

        $total = \count($regions) * \count($years) * \count($windows);

        if ($total === 0) {
            $io->error('Cannot create progress bar: input payload arrays must not be empty.');

            return Command::FAILURE;
        }

        $progressBar = $io->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Preparing queries...');
        $progressBar->start();

        $count = $this->generator->generateForRun($run, function (int $processed, array $context) use ($progressBar) {
            $window = $context['window'];
            $progressBar->setMessage(sprintf(
                'Region %s | Year %s | %s → %s',
                $context['region'],
                $context['year'],
                $window['from'] ?? '—',
                $window['to'] ?? '—'
            ));
            $progressBar->advance();
        });

        $progressBar->setMessage('Completed');
        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf('Generated %d queries for run %d.', $count, $runId));

        return Command::SUCCESS;
    }
}
