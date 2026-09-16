<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aro_interpretations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aro_version_id')->constrained()->cascadeOnDelete();
            $table->uuid('firm_id')->nullable()->index();
            $table->string('code')->nullable();
            $table->string('rule_reference');
            $table->text('question');
            $table->text('decision');
            $table->text('rationale')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('status')->default('proposed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aro_interpretations');
    }
};
