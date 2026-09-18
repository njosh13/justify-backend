<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('matter_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->string('type');
            $table->string('cost_basis');
            $table->foreignUuid('aro_version_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('fees_cents')->default(0);
            $table->unsignedBigInteger('recharges_cents')->default(0);
            $table->unsignedBigInteger('disbursements_cents')->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('wht_expected_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_method')->nullable();
            $table->timestamp('deemed_agreed_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('interest_claimed_at')->nullable();
            $table->timestamp('paid_in_full_at')->nullable();
            $table->jsonb('computed_snapshot')->nullable();
            $table->string('pdf_path')->nullable();
            $table->uuid('original_bill_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firm_id', 'number']);
            $table->index(['firm_id', 'status']);
            $table->index(['matter_id', 'status']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreign('original_bill_id')->references('id')->on('bills')->nullOnDelete();
        });

        Schema::table('chargeable_items', function (Blueprint $table) {
            $table->foreign('bill_id')->references('id')->on('bills')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION bills_guard_locked() RETURNS trigger AS $$
                BEGIN
                    IF OLD.locked_at IS NOT NULL AND (
                        NEW.number IS DISTINCT FROM OLD.number OR
                        NEW.type IS DISTINCT FROM OLD.type OR
                        NEW.cost_basis IS DISTINCT FROM OLD.cost_basis OR
                        NEW.aro_version_id IS DISTINCT FROM OLD.aro_version_id OR
                        NEW.fees_cents IS DISTINCT FROM OLD.fees_cents OR
                        NEW.recharges_cents IS DISTINCT FROM OLD.recharges_cents OR
                        NEW.disbursements_cents IS DISTINCT FROM OLD.disbursements_cents OR
                        NEW.vat_cents IS DISTINCT FROM OLD.vat_cents OR
                        NEW.wht_expected_cents IS DISTINCT FROM OLD.wht_expected_cents OR
                        NEW.total_cents IS DISTINCT FROM OLD.total_cents OR
                        NEW.computed_snapshot IS DISTINCT FROM OLD.computed_snapshot OR
                        NEW.issued_at IS DISTINCT FROM OLD.issued_at OR
                        NEW.locked_at IS DISTINCT FROM OLD.locked_at
                    ) THEN
                        RAISE EXCEPTION 'bill % is locked; issue a credit note instead of editing', OLD.id;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER bills_guard_locked BEFORE UPDATE ON bills
                    FOR EACH ROW EXECUTE FUNCTION bills_guard_locked();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS bills_guard_locked ON bills; DROP FUNCTION IF EXISTS bills_guard_locked();');
        }

        Schema::table('chargeable_items', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
        });

        Schema::dropIfExists('bills');
    }
};
