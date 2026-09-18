<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('hourly_rate_cents')->nullable();
            $table->unsignedBigInteger('fixed_amount_cents')->nullable();
            $table->timestamp('election_communicated_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['matter_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_agreements');
    }
};
