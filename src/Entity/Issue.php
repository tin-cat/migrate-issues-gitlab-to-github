<?php declare(strict_types=1);

namespace App\Entity;

enum IssueState
{
    case Open;
    case Closed;
}

class Issue {
    function __construct(
        public int $gitLabId = 0,
        public int $gitLabProjectId = 0,
        public string $gitLabUrl = '',
        public string $title = '',
        public string $description = '',
        public IssueState $state = IssueState::Open,
        public int $createdAt = 0,
        public array $labels = [],
    ) {}

    public static function buildFromGitLabApiResponse(array $data): Issue
    {
        return
            new Issue(
                gitLabId: $data['id'],
                gitLabProjectId: $data['project_id'],
                gitLabUrl: $data['web_url'],
                title: $data['title'],
                description: $data['description'],
                state:
                    match($data['state']) {
                        'open' => IssueState::Open,
                        'closed' => IssueState::Closed
                    },
                createdAt: strtotime($data['created_at'])
            );
    }
}
