<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityState;
use App\Models\EnergyCommunity;
use App\Models\MeterPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * BR-8: verifies the actual DB guarantee for the gap in
 * RegisterMeterPointIntoCommunity noted in Claude.md/NOTES.md — a meter
 * point's *first* registration has no existing blocking row for
 * lockForUpdate() to lock, so the protection has to come from InnoDB's gap
 * locking on the (meter_point_id, state) index range, not from locking rows
 * that don't exist yet. This test proves that gap lock is real on this
 * MariaDB instance, rather than asserting it in prose.
 */
class RegistrationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_connection_is_blocked_while_the_first_holds_the_registration_gap_lock(): void
    {
        $meterPoint = MeterPoint::factory()->create();
        $communityB = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $communityB->users()->attach($meterPoint->user_id, ['role' => CommunityRole::Member]);

        $connectionConfig = config('database.connections.mariadb');
        $secondConnection = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $connectionConfig['host'], $connectionConfig['port'], $connectionConfig['database']),
            $connectionConfig['username'],
            $connectionConfig['password'],
        );
        $secondConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Fail fast instead of hanging the test suite if the lock is held.
        $secondConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');

        DB::beginTransaction();

        // Exactly what RegisterMeterPointIntoCommunity runs: a locking SELECT
        // over this meter point's blocking states. It matches 0 rows (this is
        // its first registration) — the question is whether InnoDB still
        // places a gap lock on that index range despite the empty result.
        DB::table('energy_community_meter_point')
            ->where('meter_point_id', $meterPoint->id)
            ->whereIn('state', ['new', 'requested', 'message_received', 'accepted'])
            ->lockForUpdate()
            ->get();

        $blocked = false;

        try {
            $secondConnection->exec(sprintf(
                "INSERT INTO energy_community_meter_point
                    (energy_community_id, meter_point_id, state, from_date, to_date, consent_date, created_at, updated_at)
                 VALUES (%d, %d, 'new', '2026-01-01', NULL, '2025-12-20', NOW(), NOW())",
                $communityB->id,
                $meterPoint->id,
            ));
        } catch (PDOException $e) {
            // SQLSTATE HY000 / 1205: Lock wait timeout exceeded — the gap lock held.
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            DB::rollBack();
        }

        $this->assertTrue(
            $blocked,
            'Expected the concurrent insert to block on a gap lock; it went through instead — '.
            'the BR-8 guarantee for a meter point\'s first registration does not hold as assumed.',
        );
    }
}
