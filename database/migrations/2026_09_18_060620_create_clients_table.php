<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('client_number')->nullable();
            $table->string('client_type')->default('individual');
            $table->string('kra_pin', 20)->nullable();
            $table->string('id_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_withholding_agent')->default(false);
            $table->boolean('is_vat_exempt')->default(false);
            $table->string('vat_exemption_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firm_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
