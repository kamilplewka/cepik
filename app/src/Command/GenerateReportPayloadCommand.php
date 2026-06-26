<?php

namespace App\Command;

use App\Service\Ai\ReportPayloadPlanner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reports:nlp:plan', description: 'Przekształca opis raportu na payload JSON do CEPiK')]
class GenerateReportPayloadCommand extends Command
{
    public function __construct(private readonly ReportPayloadPlanner $planner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('question', InputArgument::IS_ARRAY, 'Opis raportu (możesz przekazać wiele słów)')
            ->addOption('json-only', null, InputOption::VALUE_NONE, 'Wypisz wyłącznie wynikowy JSON bez dodatkowych komunikatów');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $questionParts = $input->getArgument('question');
        $question = trim(implode(' ', \is_array($questionParts) ? $questionParts : [(string) $questionParts]));

        if ($question === '') {
            $io->error('Podaj treść pytania, np. app:reports:nlp:plan "Ile jest BMW..."');

            return Command::INVALID;
        }

        try {
            $result = $this->planner->generate($question);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $jsonOnly = (bool) $input->getOption('json-only');

        if ($result->needsClarification()) {
            if ($jsonOnly) {
                $io->writeln(json_encode([
                    'needs_clarification' => true,
                    'clarifying_questions' => $result->getClarifyingQuestions(),
                    'report_payload' => null,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return Command::SUCCESS;
            }

            $io->warning('Potrzebne dodatkowe informacje:');

            foreach ($result->getClarifyingQuestions() as $questionText) {
                $io->writeln(sprintf('- %s', $questionText));
            }

            return Command::SUCCESS;
        }

        $payload = $result->getReportPayload();

        if (!$payload) {
            $io->error('Asystent nie zwrócił gotowego payloadu.');

            return Command::FAILURE;
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $io->error('Nie można zserializować odpowiedzi do JSON.');

            return Command::FAILURE;
        }

        if ($jsonOnly) {
            $io->writeln($encoded);
        } else {
            $io->success('Payload gotowy. Skopiuj JSON poniżej:');
            $io->writeln($encoded);
        }

        return Command::SUCCESS;
    }
}
