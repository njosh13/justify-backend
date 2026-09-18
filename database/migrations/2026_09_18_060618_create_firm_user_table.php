<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firm_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('advocate');
            $table->unsignedBigInteger('hourly_rate_cents')->nullable();
            $table->timestamps();

            $table->unique(['firm_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_user');
    }
};
