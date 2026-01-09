<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // this migration will be executed per-shard via a custom command.
        Schema::create('urls', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 16)->unique();
            $table->text('original_url');
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};
