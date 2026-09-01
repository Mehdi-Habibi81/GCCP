# GCCP

GCCP is a PHP web application. This repository contains the source code and configuration for the project. Replace or expand this README with project-specific details where needed.

## Table of contents
- Features
- Requirements
- Installation
- Configuration
- Running
- Tests
- Contributing
- License
- Contact

## Features
- Web application backend written in PHP
- Two-factor authentication (2FA) setup (see auth/2fa_setup.php)
- Add other features here as the project grows

## Requirements
- PHP 7.4+ (update to the actual required PHP version)
- Composer
- MySQL / MariaDB (or another supported database)
- Common PHP extensions: ext-mbstring, ext-curl, ext-openssl (add any additional extensions required)

## Installation
1. Clone the repository:
   git clone https://github.com/Mehdi-Habibi81/GCCP.git
2. Change into the project directory:
   cd GCCP
3. Install PHP dependencies:
   composer install
4. Copy the example environment file and update values:
   cp .env.example .env
   Edit `.env` to set database credentials, app URL and other environment variables.

## Configuration
- Database: set DB_HOST, DB_NAME, DB_USER, DB_PASS in `.env`.
- 2FA: see `auth/2fa_setup.php` for the two-factor authentication setup and configuration details. Update any related settings in `.env` if applicable.
- External services: configure email, storage, or OAuth providers in `.env` as needed.

## Running (development)
- Built-in PHP server (for development only):
  php -S localhost:8000 -t public

## Running (production)
- Recommended: PHP-FPM with Nginx or Apache. Configure your webserver to serve the `public/` directory and ensure proper PHP-FPM setup.

## Tests
- If PHPUnit or other test frameworks are used, run tests with:
  ./vendor/bin/phpunit
- or via a composer script if configured:
  composer test

## Contributing
- Open issues for bugs or feature requests.
- Fork the repository, create a descriptive branch, make changes, and open a pull request.
- Include tests and update documentation for new features.
- Optionally follow coding standards (phpcs/phpstan) if configured.

## License
Specify the project license here (e.g., MIT). If none exists, add a LICENSE file to the repository.

## Contact
Maintainer: Mehdi-Habibi81
GitHub: https://github.com/Mehdi-Habibi81


