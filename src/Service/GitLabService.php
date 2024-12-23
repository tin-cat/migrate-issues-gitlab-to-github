<?php declare(strict_types=1);

namespace App\Service;

use Gitlab\Client;
use App\Entity\Issue;
use Gitlab\ResultPager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GitLabService
{
    private Client $client;

    public function init(
        string $token
    )
    {
        $this->client = new Client();
        $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
    }

    public function getIssues(
        int $projectId
    ): array
    {
        $pager = new ResultPager($this->client);
        $issues = $pager->fetchAll($this->client->issues(), 'all', [$projectId, ['state' => 'closed']]);
        if (!$issues) return [];
        return
            array_map(
                function ($data) {
                    return Issue::buildFromGitLabApiResponse($data);
                },
                $issues
            );
    }
}
