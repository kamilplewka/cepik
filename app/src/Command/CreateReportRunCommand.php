<?php

namespace App\Command;

use App\Service\Report\ReportRunFactory;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:reports:create-run', description: 'Create a new report run with the provided JSON payload')]
class CreateReportRunCommand extends Command
{
    public function __construct(
        private readonly ReportRunFactory $factory,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('report-code', InputArgument::REQUIRED, 'Unique report code (e.g. toyota-celica-active)')
            ->addArgument('payload', InputArgument::REQUIRED, 'JSON payload or path to a JSON file')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Display name for the report definition')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Description for the report definition');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reportCode = (string) $input->getArgument('report-code');
        $payloadArgument = (string) $input->getArgument('payload');

        $payload = $this->resolvePayload($payloadArgument);

        $run = $this->factory->createRun(
            $reportCode,
            $payload,
            $input->getOption('name'),
            $input->getOption('description'),
        );

        $io->success(sprintf('Report run created. ID: %d (report: %s)', $run->getId(), $reportCode));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePayload(string $argument): array
    {
        $rawPayload = $this->fetchPayloadContents($argument);

        try {
            $decoded = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(sprintf('Invalid JSON payload: %s', $exception->getMessage()));
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('Payload must decode to an associative array.');
        }

        return $decoded;
    }

    private function fetchPayloadContents(string $argument): string
    {
        $candidatePaths = [];

        if (is_file($argument)) {
            $candidatePaths[] = $argument;
        }

        $dataDirPath = $this->projectDir . '/data/' . ltrim($argument, '/');
        if (is_file($dataDirPath)) {
            $candidatePaths[] = $dataDirPath;
        }

        $basename = basename($argument);
        if ($basename !== $argument) {
            $dataBasenamePath = $this->projectDir . '/data/' . $basename;
            if (is_file($dataBasenamePath)) {
                $candidatePaths[] = $dataBasenamePath;
            }
        }

        foreach ($candidatePaths as $path) {
            $contents = file_get_contents($path);
            if ($contents !== false && $contents !== '') {
                return $contents;
            }
        }

        if (str_ends_with(strtolower($argument), '.json')) {
            throw new InvalidArgumentException(sprintf('JSON file not found at %s or %s/data/%s', $argument, $this->projectDir, $argument));
        }

        return $argument;
    }
}
