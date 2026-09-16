<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aro_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aro_version_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('rule_reference');
            $table->string('op');
            $table->string('value');
            $table->jsonb('applies_to_codes')->nullable();
            $table->string('condition_key')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['aro_version_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aro_modifiers');
    }
};
