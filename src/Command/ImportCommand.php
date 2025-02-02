<?php declare(strict_types=1);

namespace App\Command;

use Exception;
use App\Service\GitHubService;
use App\Service\GitLabService;
use App\Exception\ImportException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'app:import')]
class ImportCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $params,
        private GitLabService $gitLabService,
        private GitHubService $gitHubService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import issues')
            ->setHelp('Imports issues from the GitLab repository into the GitHub repository')
            ->addOption(
                'gitLabToken',
                null,
                InputOption::VALUE_OPTIONAL,
                'The GitLab token'
            )
            ->addOption(
                'gitLabProjectId',
                null,
                InputOption::VALUE_OPTIONAL,
                'The GitLab source project Id'
            )
            ->addOption(
                'gitHubToken',
                null,
                InputOption::VALUE_OPTIONAL,
                'The GitHub token'
            )
            ->addOption(
                'gitHubUserName',
                null,
                InputOption::VALUE_OPTIONAL,
                'The GitHub user name'
            )
            ->addOption(
                'gitHubRepository',
                null,
                InputOption::VALUE_OPTIONAL,
                'The GitHub destination repository name'
            )
            ->addOption(
                'gitHubImportDelayMs',
                null,
                InputOption::VALUE_OPTIONAL,
                'Milliseconds to wait between each issue import into GitHub to avoid triggering rate limits',
                '3000'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of issues to import. Already existing issues are not counted'
            )
            ->addOption(
                'dry',
                null,
                InputOption::VALUE_NONE,
                'Perform a dry run to see what issues would be imported but without making any actual change'
            )
        ;
    }

    private function retrieveParameter(
        InputInterface $input,
        OutputInterface $output,
        string $name
    ): string
    {
         if ($input->getOption($name)) {
            return $input->getOption($name);
        } else {
            if ($this->params->get($name)) {
                return $this->params->get($name);
            } else {
                return $this->getHelper('question')->ask($input, $output, new Question($name.'?'));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $gitLabToken = $this->retrieveParameter($input, $output, 'gitLabToken');
        $gitLabProjectId = intval($this->retrieveParameter($input, $output, 'gitLabProjectId'));
        $gitHubToken = $this->retrieveParameter($input, $output, 'gitHubToken');
        $gitLabProjectId = intval($this->retrieveParameter($input, $output, 'gitLabProjectId'));
        $gitHubUserName = $this->retrieveParameter($input, $output, 'gitHubUserName');
        $gitHubRepository = $this->retrieveParameter($input, $output, 'gitHubRepository');
        $gitHubImportDelayMs = intval($this->retrieveParameter($input, $output, 'gitHubImportDelayMs'));
        $limit = intval($input->getOption('limit'));
        $isDry = intval($input->getOption('dry'));

        try {

            $output->writeln("Retrieving issues from GitLab project #$gitLabProjectId");

            $this->gitLabService->init($gitLabToken);
            $issues = $this->gitLabService->getIssues($gitLabProjectId);

            if (!$issues) {
                $output->writeln("No issues found on GitLab project #$gitLabProjectId");
                return Command::SUCCESS;
            }

            $output->writeln(sizeof($issues)." issues found, importing".($limit ? " ($limit max)" : null));

            $this->gitHubService->init($gitHubToken, $gitHubUserName, $gitHubRepository);

            $totalIssues = sizeof($issues);
            $importOk = 0;
            $erroredIssues = [];
            $alreadyImportedIssues = [];

            $progressBar = new ProgressBar($output, $totalIssues);
            ProgressBar::setFormatDefinition('custom', '[%bar%] %current%/%max% %message%');
            $progressBar->setFormat('custom');
            $progressBar->setBarCharacter('<fg=green>█</>');
            $progressBar->setProgressCharacter("<fg=green>█</>");
            $progressBar->setEmptyBarCharacter("<fg=gray>▒</>");

            $progressBar->setMessage('');
            $progressBar->start();

            foreach ($issues as $issue) {
                $isShouldWait = false;

                $outputLine = [];
                $outputLine[] = "Issue #{$issue->gitLabId} \"{$issue->title}\"";

                try {

                    if ($this->gitHubService->isImported($issue)) {
                        $outputLine[] = "⏩ Already imported, skipping";
                        $alreadyImportedIssues[] = $issue;
                    } else {
                        if (!$isDry) {
                            $this->gitHubService->importIssue($issue);
                            $isShouldWait = true;
                            $outputLine[] = "✅ Imported";
                        } else {
                            $outputLine[] = "✅ Would be imported if no dry run";
                        }
                        $importOk ++;
                    }

                } catch (ImportException $e) {
                    $outputLine[] = "🚫 Error ({$e->getMessage()})";
                    $erroredIssues[] = $issue;
                }

                $progressBar->setMessage(implode(' / ', $outputLine));
                $progressBar->advance();

                if ($isShouldWait) {
                    usleep($gitHubImportDelayMs * 1000);
                }

                if ($limit && $importOk >= $limit) {
                    break;
                }

            }

            $progressBar->finish();

            if ($limit && $importOk >= $limit) {
                $output->writeln("Limit of $limit imported issues reached");
            }

            $output->writeln('');
            $output->writeln('');

            if ($erroredIssues) {
                $table = new Table($output);
                $table
                    ->setHeaderTitle('Failed import issues')
                    ->setHeaders(['GitLab issue id', 'title'])
                    ->setRows(
                        array_map(
                            fn ($issue) => [
                                "#{$issue->gitLabId}",
                                $issue->title
                            ],
                            $erroredIssues
                        )
                    );
                $table->render();
            }

            $output->writeln('');

            if ($alreadyImportedIssues) {
                $table = new Table($output);
                $table
                    ->setHeaderTitle('Already imported issues')
                    ->setHeaders(['GitLab issue id', 'title'])
                    ->setRows(
                        array_map(
                            fn ($issue) => [
                                "#{$issue->gitLabId}",
                                $issue->title
                            ],
                            $alreadyImportedIssues
                        )
                    );
                $table->render();
            }

            $output->writeln('');

            $table = new Table($output);
            $table
                ->setHeaderTitle($isDry ? 'Dry run result' : 'Importation result')
                ->setRows([
                    ['Total issues', $totalIssues],
                    ['Imported issues', $importOk],
                    ['Non imported issues', $totalIssues - $importOk],
                    ['Already imported issues', sizeof($alreadyImportedIssues)],
                    ['Failed imports', sizeof($erroredIssues)],
                ]);
            $table->render();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
