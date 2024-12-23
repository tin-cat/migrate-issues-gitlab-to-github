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

#[AsCommand(name: 'app:find-github-duplicates')]
class FindGitHubDuplicatesCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $params,
        private GitHubService $gitHubService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Finds duplicate issues in the GitHub repository')
            ->setHelp('Use it to fix bugs in the importation')
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
                'outputGhCommands',
                null,
                InputOption::VALUE_NONE,
                'Outputs the `gh` commands needed to delete the repeated issues on the GitHub repository leaving only one'
            );
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
        $gitHubToken = $this->retrieveParameter($input, $output, 'gitHubToken');
        $gitHubUserName = $this->retrieveParameter($input, $output, 'gitHubUserName');
        $gitHubRepository = $this->retrieveParameter($input, $output, 'gitHubRepository');
        $outputGhCommands = $input->getOption('outputGhCommands');

        try {

            $output->writeln("Retrieving GitHub issues for repository $gitHubRepository");

            $this->gitHubService->init($gitHubToken, $gitHubUserName, $gitHubRepository);

            $issues = $this->gitHubService->getCurrentIssueTitles();

            $titleCounts = array_count_values(array_values($issues));
            $repeatedIssueNumbers = array_filter(array_keys($issues), function($id) use ($issues, $titleCounts) {
                $title = $issues[$id];
                return $titleCounts[$title] > 1;
            });

            if (!$repeatedIssueNumbers) {
                $output->writeln('No repeated issues');
                return Command::SUCCESS;
            }

            $repetitions = [];
            foreach ($repeatedIssueNumbers as $issueNumber) {
                $title = $issues[$issueNumber];
                $repetitions[$title][] = $issueNumber;
            }

            if ($outputGhCommands) {

                foreach (array_values($repetitions) as $repeatedIssueNumbers) {
                    arsort($repeatedIssueNumbers);
                    foreach (array_slice($repeatedIssueNumbers, 1) as $issueNumber) {
                        $output->writeln("gh issue delete --yes $issueNumber");
                    }
                }

            } else {

                $table = new Table($output);
                $table
                    ->setHeaderTitle("Duplicate issues in the $gitHubRepository GitHub repository")
                    ->setHeaders(['title', 'issue ids'])
                    ->setRows(
                        array_map(
                            function ($title) use ($repetitions, $issues) {
                                return [
                                    $title,
                                    implode(',', $repetitions[$title])
                                ];
                            },
                            array_keys($repetitions)
                        )
                    );
                $table->render();

            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
