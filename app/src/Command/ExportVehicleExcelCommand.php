<?php

namespace App\Command;

use App\Repository\ReportRunRepository;
use App\Service\Report\ReportVehicleExporter;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:vehicles:export-excel', description: 'Export fetched CEPiK vehicle details to an Excel workbook')]
class ExportVehicleExcelCommand extends Command
{
    public function __construct(
        private readonly ReportRunRepository $runRepository,
        private readonly ReportVehicleExporter $exporter,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('run-id', InputArgument::REQUIRED, 'Identifier of the report run to export')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Optional target path for the XLSX file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $runId = (int) $input->getArgument('run-id');
        $outputPath = $input->getOption('output');

        $run = $this->runRepository->find($runId);

        if (!$run) {
            $io->error(sprintf('Report run %d not found.', $runId));

            return Command::FAILURE;
        }

        $targetPath = $this->resolveTargetPath($runId, $outputPath);

        try {
            $result = $this->exporter->export($run, $targetPath);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Saved %d vehicle rows (%d columns) to %s',
            $result['rows'],
            $result['columns'],
            $result['path']
        ));

        return Command::SUCCESS;
    }

    private function resolveTargetPath(int $runId, ?string $provided): string
    {
        if ($provided && trim($provided) !== '') {
            return $this->normalizePath($provided);
        }

        $defaultDirectory = $this->projectDir . '/var/exports';

        return sprintf('%s/report-run-%d-vehicles.xlsx', $defaultDirectory, $runId);
    }

    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);

        if (str_starts_with($trimmed, './')) {
            $trimmed = substr($trimmed, 2);
        }

        if ($trimmed[0] === '/' || $trimmed[0] === '\\') {
            return $trimmed;
        }

        if (preg_match('#^[A-Za-z]:\\\\#', $trimmed) === 1) {
            return $trimmed;
        }

        return $this->projectDir . '/' . $trimmed;
    }
}
