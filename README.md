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

This copies `.env.example` to `.env` if there isn't one yet, installs PHP dependencies,
brings the containers up, generates the app key, and runs migrations + seeders. The API is
then available at http://localhost.

Once installed, start the containers again any time with:

```bash
make up
```

Run the test suite with:

```bash
make test
```

Stop the containers with `./vendor/bin/sail down` (add `-v` to also drop the database volume).

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
./vendor/bin/sail test
```

</details>

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
