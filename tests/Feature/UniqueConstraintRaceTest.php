<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * The `unique` validation rule alone doesn't close the race between two
 * concurrent requests for the same value — both can pass validation before
 * either writes, which is exactly why EnergyCommunityController::store()
 * and ::addUser() (and MeterPointController::store()) also catch
 * UniqueConstraintViolationException around the actual insert.
 *
 * A true end-to-end proof of that race would need to interleave a second
 * connection's write between this app's validation SELECT and its own
 * INSERT — inside a single HTTP request's execution, which a synchronous
 * Feature test can't pause mid-flight without instrumenting the app itself.
 * What *is* honestly provable here, and what these tests actually check: a
 * genuine second connection racing an INSERT for the same unique value does
 * throw Laravel's UniqueConstraintViolationException (not a generic
 * QueryException, not silently succeeding) — the exact type every one of
 * those three catch clauses is written against. That the catch clauses
 * themselves then convert it to a 422 is a code-reading exercise, not
 * something this test claims to demonstrate.
 */
class UniqueConstraintRaceTest extends TestCase
{
    use RefreshDatabase;

    private function secondConnection(): PDO
    {
        $config = config('database.connections.mariadb');
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
            $config['username'],
            $config['password'],
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public function test_a_racing_duplicate_ecid_insert_throws_the_type_our_controllers_catch(): void
    {
        $second = $this->secondConnection();
        $second->exec("INSERT INTO energy_communities (ecid, state, created_at, updated_at) VALUES ('RACE-EC-1', 'new', NOW(), NOW())");

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('energy_communities')->insert([
            'ecid' => 'RACE-EC-1',
            'state' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_racing_duplicate_membership_insert_throws_the_type_our_controller_catches(): void
    {
        // Created via the second connection too, not an Eloquent factory —
        // RefreshDatabase wraps this test in an uncommitted transaction on the
        // main connection, so a factory-created parent row here wouldn't be
        // visible to the second connection yet; its FK check would just block
        // waiting for a lock instead of reaching the duplicate-key error this
        // test actually wants to provoke.
        $second = $this->secondConnection();
        $second->exec("INSERT INTO energy_communities (id, ecid, state, created_at, updated_at) VALUES (999001, 'RACE-EC-2', 'new', NOW(), NOW())");
        $second->exec("INSERT INTO users (id, name, email, password, created_at, updated_at) VALUES (999001, 'Race User', 'race@example.com', 'x', NOW(), NOW())");
        $second->exec('INSERT INTO energy_community_user (energy_community_id, user_id, role, created_at, updated_at) VALUES (999001, 999001, \'member\', NOW(), NOW())');

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('energy_community_user')->insert([
            'energy_community_id' => 999001,
            'user_id' => 999001,
            'role' => 'manager', // different role, same (community, user) pair — still a duplicate per BR-4
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
