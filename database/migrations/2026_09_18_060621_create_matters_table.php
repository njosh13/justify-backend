<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('reference')->nullable();
            $table->string('court_level')->default('none');
            $table->string('cause_number')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('value_cents')->nullable();
            $table->string('status')->default('open');
            $table->date('opened_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firm_id', 'status']);
            $table->index(['firm_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
