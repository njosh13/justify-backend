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
        Schema::create('bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('firm_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->date('dated_on')->nullable();
            $table->text('particulars');
            $table->unsignedBigInteger('claimed_cents');
            $table->unsignedBigInteger('taxed_off_cents')->nullable();
            $table->foreignUuid('aro_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('rule_reference')->nullable();
            $table->text('provenance')->nullable();
            $table->foreignUuid('chargeable_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section');
            $table->decimal('vat_rate', 9, 6)->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->string('tax_type_code', 1)->nullable();
            $table->timestamps();

            $table->unique(['bill_id', 'seq']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION bill_lines_guard_locked() RETURNS trigger AS $$
                DECLARE locked timestamp;
                BEGIN
                    SELECT locked_at INTO locked FROM bills WHERE id = COALESCE(NEW.bill_id, OLD.bill_id);
                    IF locked IS NULL THEN
                        RETURN COALESCE(NEW, OLD);
                    END IF;
                    IF TG_OP = 'DELETE' OR TG_OP = 'INSERT' THEN
                        RAISE EXCEPTION 'bill % is locked; its lines are append-only', COALESCE(NEW.bill_id, OLD.bill_id);
                    END IF;
                    IF NEW.claimed_cents IS DISTINCT FROM OLD.claimed_cents OR
                       NEW.particulars IS DISTINCT FROM OLD.particulars OR
                       NEW.seq IS DISTINCT FROM OLD.seq OR
                       NEW.section IS DISTINCT FROM OLD.section OR
                       NEW.vat_cents IS DISTINCT FROM OLD.vat_cents THEN
                        RAISE EXCEPTION 'bill % is locked; only taxed_off_cents may change', OLD.bill_id;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER bill_lines_guard_locked BEFORE INSERT OR UPDATE OR DELETE ON bill_lines
                    FOR EACH ROW EXECUTE FUNCTION bill_lines_guard_locked();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS bill_lines_guard_locked ON bill_lines; DROP FUNCTION IF EXISTS bill_lines_guard_locked();');
        }

        Schema::dropIfExists('bill_lines');
    }
};
