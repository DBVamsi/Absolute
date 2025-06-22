<div align="center">
  <img src="./app/images/Assets/banner.png" title="Pokémon Evo-Chronicles RPG Logo" alt="Pokémon Evo-Chronicles RPG Logo" />
  <h1 align="center">Pok&eacute;mon Evo-Chronicles RPG</h1>

  **Pok&eacute;mon Evo-Chronicles RPG** is an online text-based Pok&eacute;mon RPG, comprised of numerous features adapted from the official Pok&eacute;mon games, as well as entirely new features that enhance the playing experience of Pok&eacute;mon.

  <img alt="Github Issues" src="https://img.shields.io/github/issues/YourUsername/EvoChroniclesRPG?style=for-the-badge&logo=appveyor" />
  <img alt="Github Forks" src="https://img.shields.io/github/forks/YourUsername/EvoChroniclesRPG?style=for-the-badge&logo=appveyor" />
  <img alt="Github Stars" src="https://img.shields.io/github/stars/YourUsername/EvoChroniclesRPG?style=for-the-badge&logo=appveyor" />
  <br />

  <img alt="GitHub contributors" src="https://img.shields.io/github/contributors/YourUsername/EvoChroniclesRPG?style=for-the-badge">
    <a href="https://visitorbadge.io/status?path=https%3A%2F%2Fgithub.com%2FYourUsername%2FEvoChroniclesRPG">
    <img src="https://api.visitorbadge.io/api/visitors?path=https%3A%2F%2Fgithub.com%2FYourUsername%2FEvoChroniclesRPG&label=Views&countColor=%234a618f&labelStyle=upper" />
  </a>
  <br />

  <img alt="License" src="https://img.shields.io/github/license/YourUsername/EvoChroniclesRPG?style=for-the-badge&logo=appveyor" />

  Come join our comfy community over on Discord!

  <a href="https://discord.gg/SHnvbsS" target="_blank">
    <img src="https://discord.com/api/guilds/269182206621122560/widget.png?style=banner2" alt="Discord Invite Banner" />
  </a>
</div>



# Table of Contents
- [Table of Contents](#table-of-contents)
- [About The Project](#about-the-project)
  - [Tech Stack](#tech-stack)
  - [Features](#features)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Project Setup](#project-setup)
  - [Accessing PHPMyAdmin](#accessing-phpmyadmin)
  - [Chat Setup](#chat-setup)
  - [Discord Bot Setup](#discord-bot-setup)
  - [CI/CD Pipeline Setup](#cicd-pipeline-setup)
- [Setting The Root User's MySQL Password](#setting-the-root-users-mysql-password)
- [Contributing](#contributing)
- [License](#license)



# About The Project
The codebase has undergone significant refactoring to modernize its structure, improve security, and enhance maintainability using a service-oriented approach and dependency injection.

## Tech Stack
- PHP
- Node.js
- JavaScript
- TypeScript
- MySQL
- Socket.io
- MariaDB
- Linux
- CI/CD
- xDebug

## Features
Evo-Chronicles RPG has been developed from the ground up with love, and thus comes with a wide variety of features, such as:

- Dedicated Battles
- Open World Mapping
- Come Together With Clans
- Real Time In-Game Chat
- Live Trading
- Staff Panel

You may read about Evo-Chronicles RPG's features in further detail in our [FEATURES.md](docs/FEATURES.md) documentation.



# Getting Started
## Prerequisites
This project spins up [Docker](https://www.docker.com/get-started/) containers to set up the environment, so you will need that installed and configured on the machine that you're setting up this project on.

> [!NOTE]
> It is possible to set-up this project without Docker, but the steps to do so are not currently documented.

## Installation
Clone the repository to the necessary directory.

If you would like to also install Evo-Chronicles RPG's chat system and discord bot, clone this repository recursively. If you do not want them, do not clone it recursively.

```bash
git clone --recursive https://github.com/YourUsername/EvoChroniclesRPG.git
```

## Project Setup
Once you have Docker installed and have cloned this repository, all you need to do is run the [./start.sh](start.sh) script inside of your terminal.

You can do so as such:

**Windows**
```sh
bash ./start.sh
```

**Linux/MacOS**
```sh
./start.sh
```

This script does a few things in order to set-up the game on your machine:
1. Generates SSL certificates
2. Builds all necessary Docker containers
3. Sets up your database by running all necessary migrations

If you're intending on running this project on a dedicated server with your own domain name, you will need to manually set the domain name for the SSL certificates. This can be done in [./certbot/generate.sh](certbot/generate.sh).

A number of flags are included with the start script:
1. `-b` will force Docker to build even if the current commit hasn't changed
2. `-c` will force Docker to build without using cached images
3. `-v` will give you verbose messages during the SQL migration process

A [./shutdown.sh](./shutdown.sh) script is also included for safely shutting down the Docker environment and should be always be used.


## Accessing PHPMyAdmin
Once you have successfully built all Docker containers, you can access PHPMyAdmin via [https://localhost/db/](https://localhost/db/) when the environment is running.

> [!NOTE]
> The leading / is necessary, otherwise the page will fail to load necessary resources.

## Chat Setup
The source code used for Evo-Chronicles RPG's chat system can be found at `YourUsername/EvoChroniclesRPG-Chat` (link to be updated) and includes a separate, in-depth README with set-up documentation.

Evo-Chronicles RPG's docker configuration includes the necessary dockerfile to automatically build and run the RPG's chat server for you.

## Discord Bot Setup
The source code used for Evo-Chronicles RPG's Discord Bot can be found at `YourUsername/EvoChroniclesRPG-Discord-Bot` (link to be updated) and includes a separate, in-depth README with documentation regarding included features.

Evo-Chronicles RPG's docker configuration includes the necessary dockerfile to automatically build and run the Discord bot for you.

## CI/CD Pipeline Setup
Evo-Chronicles RPG uses a continue integration and deployment pipeline to automatically sync the repository ``main`` branch with the remote server.

We used to use a CI/CD pipeline through Gitlab to synchronize our code with a remote server, but since moving to Github and using Docker for development, we do not currently have a working Github CI/CD workflow configuration.

> [!NOTE]
> This project does not yet dedicated hosting and thus doesn't have a valid github workflow configuration.



# Setting The Root User's MySQL Password
> [!IMPORTANT]
> ### This is deprecated, and both the root user and absolute user will set their passwords based on the supplied values in the .env file.
> ### This section will remain in the case where you have downgraded your mariadb container image.

When you first setup Evo-Chronicles RPG, the root MySQL password is an empty string. It is highly suggested that you change this to a very secure password with the following CLI command, where `'NEW_PASSWORD'` is the password that you want the root MySQL account to have.

```sh
docker exec -it ecrpg-mysql bash
mariadb -u root -p'' password 'NEW_PASSWORD'
```

Do make sure to update the `MYSQL_ROOT_PASSWORD` `.env` value to reflect the new password that you've set.


# Testing
This project uses PHPUnit for automated testing. Version 9.x is recommended.
- Tests are located in the `tests/unit` directory.
- To run tests, execute `php phpunit.phar` from the project root (assuming `phpunit.phar` is present in the root).
- The test execution is configured by `phpunit.xml.dist`.
- See `tests/bootstrap.php` for test environment setup.


# Contributing
If you're interested in contributing to Evo-Chronicles RPG, please check out [CONTRIBUTING.md](docs/CONTRIBUTING.md) for more information.



# License
This project is licensed under MIT.

For more information about the license, check out the [LICENSE](LICENSE).
