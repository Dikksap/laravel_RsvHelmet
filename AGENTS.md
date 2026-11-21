<laravel-boost-guidelines>
# Laravel Application

## Docker environment for this repository

This project runs with Docker Compose: `app` provides Laravel/PHP and `db`
provides MariaDB. The repository is mounted at `/var/www/html` in the app
container, and the web app is exposed on host port `8001`. The host may not
have PHP or Composer installed; do not install host versions for this project.
Use Docker for all PHP, Artisan, Composer, test, and Pint commands:

```sh
docker compose up -d
docker compose exec -T app php artisan [command]
docker compose exec -T app composer [command]
docker compose exec -T app php artisan test --compact [test-path-or-filter]
docker compose exec -T app vendor/bin/pint --dirty --format agent
```

These Docker commands replace any generic host PHP/Composer setup instructions
below. If the tools are missing inside the container, inspect the Dockerfile and
Compose configuration rather than installing them on the host.

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available in the app container:

```sh
docker compose exec -T app php -v
docker compose exec -T app composer -V
```

If either command is unavailable, inspect the Dockerfile and Compose configuration
before proceeding. Do not install PHP or Composer on the host.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
docker compose exec -T app composer require laravel/boost --dev
docker compose exec -T app php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
