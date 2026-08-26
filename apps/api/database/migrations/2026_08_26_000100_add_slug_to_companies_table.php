<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * PD-2 (approved 26 August 2026): the public route identifier consumed by
 * `GET /api/v1/public/companies/{slug}`. Nullable first so existing rows can
 * be backfilled safely, then locked to NOT NULL + UNIQUE — the same two-step
 * shape used whenever a column must apply retroactively to rows that predate
 * it. Company verification and membership semantics are untouched; this
 * column carries no authorization meaning.
 *
 * Self-contained on purpose: this file must remain runnable and reproducible
 * independent of any `App\Domains\*` runtime class's future evolution or
 * removal. `App\Domains\Company\Support\CompanyIdentifier` (used by
 * `CreateCompany` for new rows going forward) intentionally shares the same
 * shape but is NOT called from here — the generation logic below is a
 * historical snapshot, inlined rather than imported.
 *
 * Backfill is a RANDOMIZED, COLLISION-RESISTANT assignment, not a
 * deterministic one: the discriminator is `Str::random(8)`, so re-running
 * this migration fresh against a different or re-seeded dataset produces
 * different slug values each time. That is fine — slugs carry no
 * authorization meaning and only need to be unique and stable once assigned,
 * which the retry loop below and the final UNIQUE constraint both guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug', 220)->nullable()->after('normalized_name');
        });

        // Randomized, collision-resistant backfill: one slug per existing row.
        // The discriminator is checked against already-backfilled rows in this
        // same run (the column has no unique index yet at this point), so a
        // collision within the batch is retried before it can ever reach the
        // UNIQUE constraint added below.
        DB::table('companies')->whereNull('slug')->orderBy('id')
            ->select('id', 'name')->each(function (object $company): void {
                $base = Str::limit(Str::slug((string) $company->name), 200, '');
                $base = $base === '' ? 'company' : $base;

                do {
                    $slug = $base.'-'.Str::lower(Str::random(8));
                } while (DB::table('companies')->where('slug', $slug)->exists());

                DB::table('companies')->where('id', $company->id)->update(['slug' => $slug]);
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
