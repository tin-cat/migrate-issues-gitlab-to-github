<?php declare(strict_types=1);

namespace App\Service;

use Github\Client;
use App\Entity\Issue;
use App\Entity\IssueState;
use Github\AuthMethod;

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
        $this->client = new Client();
        $this->client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
        $this->userName = $userName;
        $this->repositoryName = $repositoryName;

        $this->loadCurrentIssueTitles();
    }

    private function loadCurrentIssueTitles()
    {
        if ($issues = $this->client->api('issue')->all($this->userName, $this->repositoryName)) {
            $this->currentIssueTitles =
                array_map(
                    fn ($issue) => $issue['title'],
                    $issues
                );
        }
    }

    public function isImported(Issue $issue): bool
    {
        return in_array($issue->title, $this->currentIssueTitles);
    }

    public function importIssue(Issue $issue)
    {
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
    }
}
