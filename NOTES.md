# NOTES

## How far I got

All of P1 (Foundation), P2 (Registrations — the core), and P3 (Community lifecycle) are
built, tested, and pushed. 64 tests, all green, run against real MariaDB (not SQLite) —
`make test` / `./vendor/bin/sail test`.

- **P1**: `POST /api/login`, `POST/GET /api/meter-points`,
  `POST/GET /api/energy-communities`, `GET /api/energy-communities/{id}`,
  `POST /api/energy-communities/{id}/users`.
- **P2**: `POST/GET /api/energy-communities/{id}/meter-points`,
  `POST /api/registrations/{id}/transition`, `DELETE /api/registrations/{id}`.
- **P3**: `POST /api/energy-communities/{id}/activate`,
  `POST /api/energy-communities/{id}/reject`.
- **OPT**: the `ec:registrations {ecid} {--date=}` Artisan command, and the
  `accepted`-reached event with its queued listener. The OpenAPI description was
  deliberately skipped — not for lack of time, a conscious call to spend the remaining
  time on a domain seeder and a Postman collection instead (not requested by the
  assignment, but useful for manually exercising the API before submission).

## Key decisions

**Data model.** Tables, columns and enum values exactly as given in section 3/4. FK
delete behaviour chosen per relationship, not uniformly:
- `restrictOnDelete()` on `meter_points.user_id` and both FKs on
  `energy_community_meter_point` — BR-10 says registrations are never hard-deleted, so a
  hard delete of their parent (owner, meter point, or community) must not silently take
  them with it.
- `cascadeOnDelete()` on `energy_community_user`'s two FKs — a membership row has no
  standalone meaning once its user or community is actually gone.

Both `meter_points` and `energy_communities` are soft-deleted, so the app itself never
exercises these FK constraints in normal operation — they're a guardrail against a
future hard delete (e.g. a cleanup script) silently breaking BR-10, not something the
current endpoints trigger.

Check constraints enforced in the DB, not just validated in the app (`DB::statement`,
tested against real MariaDB with direct INSERTs, not just "the migration ran"):
`CHAR_LENGTH(meter_points.name) = 33`, `CHAR_LENGTH(grid_operators.identifier) = 8`,
`energy_community_meter_point.to_date >= from_date`. BR-6's "consent_date not in the
future" is **not** a check constraint — MariaDB disallows non-deterministic functions
(`CURDATE()`) in CHECK — so it's Form Request validation only.

**`meter_points.grid_operator_id`.** Deliberately not a real FK, per the assignment.
Modelled as a custom-keyed `belongsTo(GridOperator::class, 'grid_operator_id',
'identifier')`. What I'd change: make it a real FK onto `grid_operators.id`, add a
`grid_operator_id` bigint column, and keep the 8-character code only as a derived/display
value (or drop it from `meter_points` entirely and resolve it through the relation).
Migration path: add the new column nullable, backfill from the existing string via a join
on `identifier`, then swap the relation's foreign key and drop the old string column in a
follow-up migration once backfilled.

**`CommunityRole`** (manager/member) is a backed enum even though section 4 only lists
three enums to implement. Consistency and type safety; the values themselves are
unchanged from the assignment's strings.

**No API versioning** (`/api/...`, not `/api/v1/...`) — the assignment gives exact,
unversioned paths throughout.

**Auth**: Sanctum personal access tokens (`POST /api/login` returns a `plainTextToken`),
not SPA/cookie auth — matches the P1 table's "e-mail + password → token" literally, and
this is a pure JSON API with no first-party SPA client.

**Visibility vs. permission (404 vs. 403)**: a non-member gets 404 on
`GET /api/energy-communities/{id}` and on listing a community's registrations — that's
about what they can see. Action endpoints (`addUser`, `registerMeterPoint`, `transition`,
`activate`, `reject`) return 403 for a member who lacks the manager role — that's about
permission on a resource they already know exists. Applied consistently across every
endpoint that needed one or the other.

## BR-8: what the race-safety guarantee actually is

`RegisterMeterPointIntoCommunity` wraps the BR-7 overlap check and the insert in one
`DB::transaction()`, having first run `lockForUpdate()` over the meter point's existing
*blocking* registrations. For a meter point with at least one existing blocking
registration, this is a straightforward row lock: a second concurrent request checking
the same meter point blocks until the first transaction commits, then sees its result.

The harder case is a meter point's **first** registration — there's no existing row to
lock. The guarantee there rests on InnoDB gap locking: `SELECT ... FOR UPDATE` with a
range condition (`WHERE meter_point_id = ? AND state IN (...)`) places a gap/next-key
lock on that index range even when it matches zero rows, under MariaDB's default
REPEATABLE READ isolation.

I did not want to just assert either case — `tests/Feature/RegistrationConcurrencyTest.php`
has two tests, each opening a second, independent PDO connection and holding the app's
exact locking SELECT open in an uncommitted transaction on the first one, then confirming
a concurrent INSERT for the same `meter_point_id` from the second connection blocks and
fails with `Lock wait timeout exceeded` rather than going through — once against zero
matching rows (the gap-lock case) and once against one existing accepted registration
(the standard row-lock case). Both pass on this MariaDB 11 instance.

BR-9 transitions use a different mechanism: an atomic `UPDATE ... WHERE id = ? AND
state = <state read before the call>`. Zero affected rows means either the state moved
under the caller or the transition was never legal from the state they read — both
surface as 409, not a silent overwrite or a 500.

## Where I deviated from the document, and why

- **Route path hyphenation**: the assignment's own P1/P3 tables render the two
  parameterized community routes without a hyphen
  (`/api/energycommunities/{energyCommunity}/...`), but every unparameterized route on
  the same page, and the Request Bodies code block a page later for the identical
  parameterized route, use `energy-communities` (hyphenated). Treated the no-hyphen form
  as a PDF table-wrapping artifact and used `energy-communities` throughout, consistently.
- Registration and community-lifecycle endpoints don't accept a client-supplied target
  state for "delete" — `DELETE /api/registrations/{id}` derives the correct BR-9
  transition from the registration's current state itself (`accepted` → `deactivated`,
  everything else non-terminal → `removed`), since the assignment describes this as
  mapping to "the appropriate transition," not an arbitrary one chosen by the caller.

## Where I was unsure — assumptions made

- **`grid_operators.identifier` format**: a call transcript I had access to claimed
  operator identifiers "always start with AT" — the PDF only shows Austrian examples and
  states explicitly that `ecid` needs no format validation; I did not extend that to
  requiring an "AT" prefix on `identifier` either, validating only its length (8) and
  uniqueness. If operators from other countries are real in production, enforcing an
  "AT" prefix would have been actively wrong.
- **Pagination page size**: used Laravel's default (15) everywhere lists are paginated;
  the assignment doesn't specify one.
- **`GET /api/energy-communities/{id}/meter-points` visibility**: not explicitly scoped
  by BR-11 in the assignment text (BR-11 talks about communities and meter points, not
  this specific nested list), but I applied the same "member or admin, else 404"
  convention as the community's own `show` endpoint, since a registration list is
  strictly more sensitive than the community metadata it's nested under.

## What's missing / what I'd do next, prioritized

1. **OpenAPI description** — the one OPT item not attempted; a conscious trade-off
   against the seeder/Postman collection and the fresh-clone verification below, not an
   oversight.
2. **`meter_points.grid_operator_id`** as a real FK — see the data model section above
   for the migration path.
3. ~~A second BR-8 concurrency test for the "existing blocking registration" case.~~
   Done: `RegistrationConcurrencyTest` now covers both the gap-lock case (a meter
   point's first registration) and the standard row-lock case (an existing accepted
   registration already there).
4. ~~Pint run and a final `php artisan test` pass before packaging.~~ Done: Pint fixed
   two files (an unused import, some formatting), all tests still pass afterwards.
   Laravel's default boilerplate tests (`tests/Feature/ExampleTest.php`,
   `tests/Unit/ExampleTest.php`) removed — they didn't test anything about this domain.
5. Larastan was never installed or run — not in `composer.json`'s dev dependencies by
   default, and I didn't add it. `Model::shouldBeStrict()` is on outside production, which
   caught at least one real bug during development (see the `users.is_admin` note in
   `Overview.md`), but that's a runtime check, not static analysis.

## The submission checklist's clean-state requirement — actually verified, not assumed

"`composer install && php artisan migrate --seed && php artisan test` works from a clean
state" is easy to get wrong when a long-running dev container accumulates state that a
fresh checkout won't have. I cloned the pushed repo into a scratch directory (not this
working copy) and ran through exactly that sequence — it caught a real bug:
`phpunit.xml` referenced `tests/Unit`, which locally still existed as an empty directory
after `tests/Unit/ExampleTest.php` was deleted, but git doesn't track empty directories,
so a genuinely fresh clone never had it at all. `php artisan test` failed immediately
with "Test directory tests/Unit not found," before a single test ran — which would have
sunk the whole test suite in review despite 63 passing tests in my own environment.
Fixed by dropping the empty `Unit` testsuite from `phpunit.xml` (there are no unit tests
in this project; everything is a Feature test against the real API). Re-verified against
the same fresh clone afterwards — 64/64 passing (two more than the number above once the
second BR-8 concurrency test landed).

## Seeder and Postman collection (not requested by the assignment)

`database/seeders/DatabaseSeeder.php` seeds fixed, known data — not random factories —
so it's reproducible: 4 users (`admin@example.com`, `manager@example.com`,
`member@example.com`, `owner@example.com`, all password `password`), 2 grid operators,
3 metering points, 3 energy communities spanning all three `EnergyCommunityState`
values, and 3 registrations spanning `new`/`requested`/`accepted`.

`postman/` has a matching Postman collection and environment (see the README's "Trying
the API with Postman" section) — every request was run once by hand against the seeded
data to confirm it returns what it claims to before committing it, not just written and
assumed correct.
