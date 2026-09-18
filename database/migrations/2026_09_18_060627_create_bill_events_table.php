<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['bill_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_events');
    }
};
