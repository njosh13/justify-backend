<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('plan')->default('solo');
            $table->string('isolation')->default('shared');
            $table->string('kra_pin', 20)->nullable();
            $table->string('lsk_firm_number')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('rounding_policy')->default('shilling_half_up');
            $table->string('default_cost_basis')->default('advocate_client');
            $table->string('bill_number_prefix', 20)->default('FN');
            $table->unsignedInteger('bill_sequence')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
