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
make install
```

That one command does everything needed to go from a fresh checkout to a running API:

1. Copies `.env.example` to `.env` if there isn't one yet.
2. Installs PHP dependencies via a disposable `laravelsail/php83-composer` container — no
   local PHP or Composer required.
3. Brings up the containers (`laravel.test` on PHP 8.3, `mariadb` running MariaDB 11).
4. Generates the app key (`artisan key:generate`).
5. Runs migrations and seeds the database (`artisan migrate --seed`) — creates all tables
   and a small fixed dataset (grid operators, users, metering points, energy communities in
   various states) described in `database/seeders/DatabaseSeeder.php`.

The API is then available at http://localhost. The database itself isn't something you need
to set up separately — the `mariadb` service in `compose.yaml` and the `DB_*` variables in
`.env.example` already point the app at it (host `mariadb`, database `laravel`, user `sail`).

Once installed, start the containers again any time (without repeating the steps above) with:

```bash
make up
```

Stop them with `./vendor/bin/sail down` (add `-v` to also drop the database volume).

<details>
<summary>Equivalent commands, if you'd rather not use <code>make</code></summary>

```bash
cp .env.example .env

# Skip this step if you already have PHP 8.2+/Composer locally and prefer
# to just run `composer install` instead.
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

</details>

### Migrations

`make install` already runs migrations and seeds the database, but while working on the
project you'll often want to re-run them directly:

```bash
./vendor/bin/sail artisan migrate           # apply any new migrations
./vendor/bin/sail artisan migrate:fresh     # drop every table and re-run all migrations
./vendor/bin/sail artisan migrate:fresh --seed   # same, plus reseed the fixed dataset
```

`migrate:fresh --seed` is the quickest way back to a known-good state — useful after manually
testing writes through Postman or the API directly.

## Running tests

```bash
make test
```

Tests run against a real MariaDB database (a separate `testing` database on the same
container, configured in `phpunit.xml`), not SQLite — constraint behaviour (foreign keys,
check constraints, unique indexes) needs to be real for BR-7/BR-8 to mean anything.

To run a single test file or a subset by name, use `sail artisan test` directly instead:

```bash
./vendor/bin/sail artisan test tests/Feature/RegistrationTest.php
./vendor/bin/sail artisan test --filter=test_br7_open_ended_accepted_registration_blocks_a_later_overlapping_one
```

## Trying the API with Postman

`postman/` has a collection and a matching environment covering all endpoints, pre-filled
with request bodies that work against the seeded data (`database/seeders/DatabaseSeeder.php`
— four users, all with password `password`: `admin@example.com`, `manager@example.com`,
`member@example.com`, `owner@example.com`).

1. Import both `postman/Energy-Communities-API.postman_collection.json` and
   `postman/Energy-Communities-API.postman_environment.json`, and select the environment.
2. Run each of the four requests in **Auth** once — their test scripts write the returned
   token into the environment (`manager_token`, `owner_token`, ...), which every other
   request references.
3. The rest of the collection is grouped by resource and works from a fresh `make install`
   without any other setup. A couple of requests demonstrate an expected failure on purpose
   (activating a community without an accepted generation registration, BR-12) — that's the
   point, not a bug.

## API description

`openapi.yaml` (OpenAPI 3.0.3) describes all 13 endpoints — request/response schemas,
auth, and status codes including the domain-specific ones (409 for a BR-7 overlap or an
illegal BR-9 transition, 404 vs. 403 for visibility vs. permission). Validated with
`npx @redocly/cli lint openapi.yaml`. Paste it into [Swagger Editor](https://editor.swagger.io)
or a local Redoc/Swagger UI instance for the interactive rendering.

## License

Laravel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
