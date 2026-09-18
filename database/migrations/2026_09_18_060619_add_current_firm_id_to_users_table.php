<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('current_firm_id')->nullable()->after('remember_token')->constrained('firms')->nullOnDelete();
            $table->string('lsk_number')->nullable()->after('current_firm_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_firm_id');
            $table->dropColumn('lsk_number');
        });
    }
};
