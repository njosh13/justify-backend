<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aro_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aro_version_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedTinyInteger('schedule');
            $table->string('label');
            $table->string('rule_reference');
            $table->string('basis_type')->default('none');
            $table->string('computation');
            $table->string('scale_variant')->default('none');
            $table->string('applies_cost_basis')->default('non_contentious');
            $table->boolean('is_instruction_fee')->default(false);
            $table->string('unit_label')->nullable();
            $table->unsignedInteger('units_included')->nullable();
            $table->unsignedBigInteger('included_amount_cents')->nullable();
            $table->jsonb('params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['aro_version_id', 'code']);
            $table->index(['aro_version_id', 'schedule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aro_items');
    }
};
