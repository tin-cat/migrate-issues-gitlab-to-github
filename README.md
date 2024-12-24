# migrate-issues-gitlab-to-github
A tool to migrate issues from a repository at GitLab to a repository at GitHub.

## Requiremens
- PHP 8
- Composer

## Usage
Clone the repository to a machine with PHP 8:
```bash
git clone https://github.com/tin-cat/migrate-issues-gitlab-to-github.git
```

Install dependencies
```bash
composer install
```

You can either setup the importation by creating an `.env.local` file, by providing all the needed setup via parameters, or by calling the tool with no parameters to get asked interactively.

**Setup with an .env.local file**

Copy the provided `.env.local.example` file as reference and fill in your configuration parameters:

```
# GitLab configuration
GITLAB_TOKEN=
GITLAB_PROJECT_ID=

# GitHub configuration
GITHUB_TOKEN=
GITHUB_USERNAME=
GITHUB_REPOSITORY=
```

To run the importation
```bash
php bin/console app:import
```

**Specifying all configuration parameters in the command line instead**

List of available parameters:

```bash
php bin/console app:import --help
```

Run the importation

```bash
php bin/console app:import \
--gitLabToken=<Your GitLab token> \
--gitLabProjectId=<Your source GitLab project id> \
--gitHubToken=<Your GitHub token> \
--gitHubUserName=<Your GitHub user name> \
--gitHubRepository=<Your destination GitHub repository name>
```

Please note that all authentication and repository identification parameters are required if you haven't set them up in an `.env.local` file.

## Features
- Tries to be gentle with APIs to avoid triggering rate limits.
- Tries to be idempotent so running the tool multiple times shouldn't create duplicates.

## Downsides
It only imports the following information from issues:
- title
- body
- status (Either open or closed)

## Please notice
- Imported issues on GitHub are assigned to the authenticated user via GITHUB_TOKEN

## License
GNU General Public License v3

## Contribute
Please feel free to contribute your issues and merge requests.

## Credits
Thanks to:
- [KNP Labs](https://github.com/KnpLabs) and their contributors for their PHP API client for GitHub [php-github-api](https://github.com/KnpLabs/php-github-api)
- The creators and contributors of the [GitLab PHP API Client](https://github.com/GitLabPHP)

## Other similar tools
This tool is designed as a very simplistic migration with some limitations. It is also designed so it is easily modifiable to solve your own issue migration needs.

There are other projects aimed also at migrating issues from GitLab to GitHub, and are arguably better built and more feature-rich than this solution:

- [gitlab-github-migrate](https://github.com/wollzelle/gitlab-github-migrate)
- [node-gitlab-2-github](https://github.com/piceaTech/node-gitlab-2-github)
