<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matter_classifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('aro_version_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('aro_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('basis_cents')->nullable();
            $table->string('basis_limb')->nullable();
            $table->string('scale')->nullable();
            $table->string('posture')->nullable();
            $table->jsonb('certificates')->nullable();
            $table->boolean('contested')->default(false);
            $table->boolean('is_exempt')->default(false);
            $table->string('exemption_reason')->nullable();
            $table->foreignId('exempted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('exempted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['matter_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matter_classifications');
    }
};
