# Energy Communities API

A JSON API modeling a simplified slice of an energy-sharing platform: **energy communities**,
**metering points**, and the **registrations** that connect the two, plus the users who own
and administer them.

An energy community lets participants share locally produced electricity — one or more PV
plants feed surplus into the grid, and instead of being sold to a supplier, that energy is
allocated to the community's other members. A metering point is a physical grid connection
(a house, a flat, a PV plant); it doesn't simply belong to a community, it has to be
**registered** with the grid operator first, and that registration carries its own state
machine and validity period.

See [`NOTES.md`](./NOTES.md) for the data model decisions, trade-offs, and open questions.

## Stack

- Laravel 12, PHP 8.3
- MariaDB 11
- Laravel Sanctum (token auth)
- Docker via [Laravel Sail](https://laravel.com/docs/sail)

## Getting started

Requirements: Docker and Docker Compose. No local PHP or Composer installation is needed —
dependencies are installed through a disposable Composer container.

```bash
cp .env.example .env

# Install PHP dependencies (skip this if you already have PHP 8.2+/Composer locally
# and prefer to just run `composer install` instead)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/opt" \
    -w /opt \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

The API is then available at http://localhost.

Run the test suite with:

```bash
./vendor/bin/sail test
```

Stop the containers with `./vendor/bin/sail down` (add `-v` to also drop the database volume).

### Port conflicts

Sail binds MariaDB to host port 3306 and the app to host port 80 by default. If either is
already taken by another local service (e.g. a native MySQL/MariaDB install), override it in
`.env` before running `sail up`:

```dotenv
APP_PORT=8000
FORWARD_DB_PORT=13306
```

Containers still reach each other over their internal default ports regardless of this
override — it only affects access from the host machine.

## License

Laravel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
