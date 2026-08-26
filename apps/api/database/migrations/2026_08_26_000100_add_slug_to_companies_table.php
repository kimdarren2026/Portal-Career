<?php

use App\Domains\Company\Support\CompanyIdentifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PD-2 (approved 26 August 2026): the public route identifier consumed by
 * `GET /api/v1/public/companies/{slug}`. Nullable first so existing rows can
 * be backfilled safely, then locked to NOT NULL + UNIQUE — the same two-step
 * shape used whenever a column must apply retroactively to rows that predate
 * it. Company verification and membership semantics are untouched; this
 * column carries no authorization meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug', 220)->nullable()->after('normalized_name');
        });

        // Deterministic backfill: one slug per existing row, generated from
        // its current name exactly as CreateCompany now does for new rows.
        DB::table('companies')->whereNull('slug')->orderBy('id')
            ->select('id', 'name')->each(function ($company): void {
                DB::table('companies')->where('id', $company->id)
                    ->update(['slug' => CompanyIdentifier::slug((string) $company->name)]);
            });

        DB::statement('ALTER TABLE companies ALTER COLUMN slug SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_companies_slug ON companies (slug)');
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
