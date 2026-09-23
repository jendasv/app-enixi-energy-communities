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
        Schema::create('grid_operators', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('identifier', 8)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // meter_points.grid_operator_id stores this string, not the id column —
        // enforce the exact length the domain requires at the DB level too.
        DB::statement('ALTER TABLE grid_operators ADD CONSTRAINT grid_operators_identifier_length CHECK (CHAR_LENGTH(identifier) = 8)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grid_operators');
    }
};
