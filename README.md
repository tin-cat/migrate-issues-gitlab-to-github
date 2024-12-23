# migrate-issues-gitlab-to-github
A tool to migrate issues from a repository at GitLab to a repository at GitHub.

## Requiremens
- PHP 8

## Usage
You can either setup the importation by creating an `.env.local` file (Copy the provided `.env.local.example` file as reference), or by providing all the needed setup via parameters.

To run the importation
```bash
php bin/console app:import
```

Get the list of available parameters:

```bash
php bin/console app:import --help
```

Please note that some parameters are required if you haven't set them up in an `.env.local` file.

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
