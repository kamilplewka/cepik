<?php

namespace App\Command;

use App\Repository\ReportRunRepository;
use App\Service\Report\ReportResultAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:aggregate-run', description: 'Aggregate CEPiK results for a completed run')]
class AggregateReportRunCommand extends Command
{
    public function __construct(
        private readonly ReportRunRepository $runRepository,
        private readonly ReportResultAggregator $aggregator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Identifier of the report run to aggregate');
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

        $progressBar = null;

        try {
            $result = $this->aggregator->aggregateRun($run, function (int $processed, ?\App\Entity\ReportQuery $query, int $total) use (&$progressBar, $io) {
                if ($progressBar === null) {
                    $progressBar = $io->createProgressBar($total);
                    $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                    $progressBar->setMessage(sprintf('Total queries: %d', $total));
                    $progressBar->start();
                }

                if ($processed === 0) {
                    return;
                }

                $request = $query->getRequestParams();
                $meta = $request['meta'] ?? [];
                $queryParams = $request['query'] ?? $request;
                $count = $query->getAggregatedCount() ?? 0;
                $windowMeta = \is_array($meta['date_window'] ?? null) ? $meta['date_window'] : [];
                $from = $windowMeta['from'] ?? $queryParams['data-od'] ?? '—';
                $to = $windowMeta['to'] ?? $queryParams['data-do'] ?? '—';
                $label = sprintf(
                    '[%d/%d] Region %s | Year %s | Window %s → %s | Count %d',
                    $processed,
                    $total,
                    $meta['wojewodztwo'] ?? ($queryParams['wojewodztwo'] ?? 'n/a'),
                    $meta['year'] ?? ($queryParams['filter[rok-produkcji]'] ?? 'n/a'),
                    $from,
                    $to,
                    $count,
                );

                $progressBar->setMessage($label);
                $progressBar->advance();
            });
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($progressBar) {
            $progressBar->setMessage('Aggregation complete');
            $progressBar->finish();
            $io->newLine(2);
        }

        $io->success(sprintf('Aggregation ready. Result ID: %d', $result->getId()));

        return Command::SUCCESS;
    }
}
