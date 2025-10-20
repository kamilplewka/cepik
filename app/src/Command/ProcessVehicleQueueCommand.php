<?php

namespace App\Command;

use App\Enum\ReportVehicleStatus;
use App\Service\Report\ReportVehicleProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:vehicles:process-queue', description: 'Consume pending CEPiK vehicle detail requests')]
class ProcessVehicleQueueCommand extends Command
{
    public function __construct(private readonly ReportVehicleProcessor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of vehicles to process in one batch', 10)
            ->addOption('ignore-attempts', null, InputOption::VALUE_NONE, 'Do not stop after reaching max attempts (keep retrying)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $ignoreAttempts = (bool) $input->getOption('ignore-attempts');

        $retryableHalts = [
            'Idle timeout reached while fetching vehicle details.',
            'CEPiK API limit reached (API-ERR-100011).',
        ];

        $totalProcessed = 0;

        while (true) {
            $progressBar = null;

            $batchResult = $this->processor->processBatch(
                $limit,
                $ignoreAttempts,
                function (int $processedCount, ?\App\Entity\ReportVehicle $vehicle, int $total) use (&$progressBar, $io) {
                    if ($progressBar === null) {
                        $progressBar = $io->createProgressBar($total);
                        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                        $progressBar->setMessage(sprintf('Queued: %d', $total));
                        $progressBar->start();
                    }

                    if ($processedCount === 0 || $vehicle === null) {
                        return;
                    }

                    $status = $vehicle->getStatus();
                    $color = match ($status) {
                        ReportVehicleStatus::Succeeded => 'green',
                        ReportVehicleStatus::Retrying => 'yellow',
                        ReportVehicleStatus::Failed => 'red',
                        default => 'cyan',
                    };

                    $label = sprintf(
                        '[%d/%d] Vehicle %s | Attempts %d | Status %s',
                        $processedCount,
                        $total,
                        $vehicle->getVehicleId(),
                        $vehicle->getAttempts(),
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
                    $io->warning('No pending vehicle requests were found.');
                } else {
                    $io->success(sprintf('Processed %d queued vehicles.', $totalProcessed));
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
                $io->warning(sprintf('%s Waiting 5 seconds before retrying…', $reason));
                sleep(5);

                if (!\in_array($reason, $retryableHalts, true)) {
                    $io->error(sprintf('Processing halted: %s', $reason));

                    return Command::FAILURE;
                }

                continue;
            }

            if ($batchResult['retry_pending'] || $batchResult['failures'] > 0) {
                $io->warning('Retryable vehicle requests detected. Waiting 5 seconds before fetching the queue again…');
                sleep(5);
                continue;
            }

            if ($batchResult['processed'] < $limit) {
                $io->success(sprintf('Processed %d queued vehicles.', $totalProcessed));

                return Command::SUCCESS;
            }
        }
    }
}
