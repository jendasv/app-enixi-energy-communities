<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The table already has (meter_point_id, state) for the BR-7 overlap
        // check. Reject and the community-registrations list both filter by
        // (energy_community_id, state) instead, which previously only had a
        // single-column index on energy_community_id (from the FK) — MariaDB
        // used that to narrow down to the community, then filtered `state`
        // without index support (confirmed with EXPLAIN, not assumed).
        Schema::table('energy_community_meter_point', function (Blueprint $table) {
            $table->index(['energy_community_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('energy_community_meter_point', function (Blueprint $table) {
            $table->dropIndex(['energy_community_id', 'state']);
        });
    }
};
