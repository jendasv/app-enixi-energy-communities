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
        Schema::create('meter_points', function (Blueprint $table) {
            $table->id();
            $table->string('name', 33)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('energy_direction');
            // Not a real FK: this is grid_operators.identifier (a string code),
            // not grid_operators.id. See NOTES.md for what a cleaner shape would be.
            $table->string('grid_operator_id', 10);
            $table->timestamps();
            $table->softDeletes();

            $table->index('grid_operator_id');
        });

        DB::statement('ALTER TABLE meter_points ADD CONSTRAINT meter_points_name_length CHECK (CHAR_LENGTH(name) = 33)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_points');
    }
};
