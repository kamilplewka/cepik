<?php

namespace App\Command;

use App\Service\Report\ReportQueryProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:process-queue', description: 'Consume pending CEPiK queries for all report runs')]
class ProcessReportQueriesCommand extends Command
{
    public function __construct(private readonly ReportQueryProcessor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of queries to process in one batch', 10)
            ->addOption('ignore-attempts', null, InputOption::VALUE_NONE, 'Do not stop after reaching max attempts (keep retrying)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $ignoreAttempts = (bool) $input->getOption('ignore-attempts');
        $retryableHalts = [
            'Idle timeout reached while querying CEPiK.',
            'CEPiK API limit reached (API-ERR-100011).',
        ];

        $totalProcessed = 0;

        while (true) {
            $progressBar = null;

            $batchResult = $this->processor->processBatch(
                $limit,
                $ignoreAttempts,
                function (int $processedCount, ?\App\Entity\ReportQuery $query, int $total) use (&$progressBar, $io) {
                    if ($progressBar === null) {
                        $progressBar = $io->createProgressBar($total);
                        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                        $progressBar->setMessage(sprintf('Queued: %d', $total));
                        $progressBar->start();
                    }

                    if ($processedCount === 0 || $query === null) {
                        return;
                    }

                    $status = $query->getStatus();
                    $color = match ($status) {
                        \App\Enum\ReportQueryStatus::Succeeded => 'green',
                        \App\Enum\ReportQueryStatus::Retrying => 'yellow',
                        \App\Enum\ReportQueryStatus::Failed => 'red',
                        default => 'cyan',
                    };

                    $request = $query->getRequestParams();
                    $meta = $request['meta'] ?? [];
                    $queryParams = $request['query'] ?? $request;
                    $label = sprintf(
                        '[%d/%d] Region %s | Year %s | Attempts %d | Status %s',
                        $processedCount,
                        $total,
                        $meta['wojewodztwo'] ?? ($queryParams['wojewodztwo'] ?? 'n/a'),
                        $meta['year'] ?? ($queryParams['filter[rok-produkcji]'] ?? 'n/a'),
                        $query->getAttempts(),
                        strtoupper($status->value),
                    );

                    $progressBar->setMessage(sprintf('Last: %s', strtoupper($status->value)));
                    $progressBar->advance();

                    $progressBar->clear();
                    $io->writeln(sprintf('<fg=%s>%s</>', $color, $label));
                    $progressBar->display();
                }
            );

            if ($batchResult['processed'] === 0) {
                if ($totalProcessed === 0) {
                    $io->warning('No pending queries were found.');
                } else {
                    $io->success(sprintf('Processed %d queued queries.', $totalProcessed));
                }

                return Command::SUCCESS;
            }

            $totalProcessed += $batchResult['processed'];

            if ($progressBar) {
                $progressBar->setMessage($batchResult['halted'] ? 'Processing halted' : 'Batch complete');
                $progressBar->finish();
                $io->newLine(2);
            }

            if ($batchResult['halted']) {
                $reason = $batchResult['halt_reason'] ?? 'unknown reason';

                if (\in_array($reason, $retryableHalts, true)) {
                    $io->warning(sprintf('%s Waiting 5 seconds before retrying…', $reason));
                    sleep(5);
                    continue;
                }

                $io->error(sprintf('Processing halted: %s', $reason));

                return Command::FAILURE;
            }

            if ($batchResult['processed'] < $limit) {
                $io->success(sprintf('Processed %d queued queries.', $totalProcessed));

                return Command::SUCCESS;
            }
        }
    }
}
