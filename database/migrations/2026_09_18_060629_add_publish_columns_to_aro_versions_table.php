<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aro_versions', function (Blueprint $table) {
            $table->foreignId('published_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by');
        });
    }

    public function down(): void
    {
        Schema::table('aro_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
    }
};
