<?php declare(strict_types=1);

namespace App\Service;

use Exception;
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
        try {
            $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
        } catch (Exception $e) {;
            throw new Exception("Error authenticating at GitLab: {$e->getMessage()}");
        }
    }

    public function getIssues(
        int $projectId
    ): array
    {
        try {
            $pager = new ResultPager($this->client);
            $issues = $pager->fetchAll($this->client->issues(), 'all', [$projectId]);
            if (!$issues) return [];
            return
                array_map(
                    function ($data) {
                        return Issue::buildFromGitLabApiResponse($data);
                    },
                    $issues
                );
        } catch (Exception $e) {;
            throw new Exception("Error retrieving GitLab issues: {$e->getMessage()}");
        }
    }
}
