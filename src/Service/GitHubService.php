<?php declare(strict_types=1);

namespace App\Service;

use Exception;
use Github\Client;
use App\Entity\Issue;
use Github\AuthMethod;
use Github\ResultPager;
use App\Entity\IssueState;

class GitHubService
{
    private Client $client;
    private string $userName;
    private string $repositoryName;

    private array $currentIssueTitles = [];

    public function init(
        string $token,
        string $userName,
        string $repositoryName
    )
    {
        try {
            $this->client = new Client();
            $this->client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
        } catch (Exception $e) {;
            throw new Exception("Error authenticating at GitHub: {$e->getMessage()}");
        }

        $this->userName = $userName;
        $this->repositoryName = $repositoryName;

        $this->loadCurrentIssues();
    }

    private function loadCurrentIssues()
    {
        try {
            $pager = new ResultPager($this->client);
            $issues = $pager->fetchAll($this->client->api('issue'), 'all', ['username' => $this->userName, 'repository' => $this->repositoryName, 'params' => ['state' => 'all']]);
            if ($issues) {
                foreach ($issues as $issue) {
                    $this->currentIssueTitles[$issue['id']] = $issue['title'];
                }
            }
        } catch (Exception $e) {
            throw new Exception("Error retrieving issues from GitHub: {$e->getMessage()}");
        }
    }

    public function isImported(Issue $issue): bool
    {
        return in_array($issue->title, array_values($this->currentIssueTitles));
    }

    public function importIssue(Issue $issue)
    {
        try {
            $result = $this->client->api('issue')->create(
                $this->userName,
                $this->repositoryName,
                [
                    'title' => $issue->title,
                    'body' => $issue->description
                ]
            );
            if ($issue->state == IssueState::Closed) {
                $this->client->api('issue')->update(
                    $this->userName,
                    $this->repositoryName,
                    $result['number'],
                    ['state' => 'closed']
                );
            }
        } catch (Exception $e) {;
            throw new Exception("Error adding issue to GitHub: {$e->getMessage()}");
        }
    }
}
