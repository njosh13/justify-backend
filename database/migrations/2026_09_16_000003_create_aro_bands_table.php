<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aro_bands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aro_item_id')->constrained()->cascadeOnDelete();
            $table->string('scale')->nullable();
            $table->unsignedBigInteger('lower_cents')->default(0);
            $table->unsignedBigInteger('upper_cents')->nullable();
            $table->unsignedBigInteger('fixed_cents')->nullable();
            $table->decimal('rate', 9, 6)->nullable();
            $table->unsignedBigInteger('floor_cents')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['aro_item_id', 'scale', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aro_bands');
    }
};
