<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bill_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->date('received_at');
            $table->string('allocated_to')->default('fees');
            $table->string('wht_certificate_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bill_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
