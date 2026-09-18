<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargeable_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advocate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->foreignUuid('aro_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->date('occurred_on');
            $table->decimal('quantity', 12, 4)->nullable();
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('basis_override_cents')->nullable();
            $table->string('scale_override')->nullable();
            $table->string('posture_override')->nullable();
            $table->jsonb('modifier_codes')->nullable();
            $table->jsonb('modifier_amounts')->nullable();
            $table->unsignedBigInteger('entered_cents');
            $table->unsignedBigInteger('computed_minimum_cents')->nullable();
            $table->string('computed_bound')->nullable();
            $table->unsignedBigInteger('computed_ceiling_cents')->nullable();
            $table->jsonb('computed_snapshot')->nullable();
            $table->text('uplift_justification')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->foreignUuid('bill_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['matter_id', 'occurred_on']);
            $table->index(['firm_id', 'bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chargeable_items');
    }
};
