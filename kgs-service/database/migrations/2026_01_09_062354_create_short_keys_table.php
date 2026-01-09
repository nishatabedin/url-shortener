<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('short_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 16)->unique(); 
            $table->unsignedTinyInteger('status')->default(0); // 0=unused, 1=used
            $table->timestampNullable('reserved_at');
            $table->timestampNullable('used_at');
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        // Single-row counter table
        Schema::create('key_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        // Insert one counter row
        DB::table('key_counters')->insert(['value' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('short_keys');
        Schema::dropIfExists('key_counters');
    }
};
