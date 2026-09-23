<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The registration. Never hard-deleted (BR-10) — no soft-delete column,
        // "deleting" one is a state transition (BR-9) instead.
        Schema::create('energy_community_meter_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('energy_community_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_point_id')->constrained()->restrictOnDelete();
            $table->string('state');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->date('consent_date');
            $table->integer('status_code')->nullable();
            $table->timestamps();

            // BR-7 overlap checks are scoped to a meter point and its blocking
            // states first, then filtered by date — this index serves that lookup.
            $table->index(['meter_point_id', 'state']);
        });

        DB::statement('ALTER TABLE energy_community_meter_point ADD CONSTRAINT ecmp_to_date_after_from_date CHECK (to_date IS NULL OR to_date >= from_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_community_meter_point');
    }
};
