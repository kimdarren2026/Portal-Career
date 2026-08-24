<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * PUT /candidate/{collection}: full replacement of one collection in one transaction.
 * A row whose id is absent from the payload is deleted — omission is removal.
 */
abstract class SyncCandidateCollection
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @return list<string> */
    abstract protected function fields(): array;

    /**
     * @param  list<array<string, mixed>>  $items  Already validated rows.
     * @throws ModelNotFoundException When an id is outside this candidate's collection.
     */
    public function execute(User $actor, CandidateProfile $profile, string $slug, array $items): void
    {
        /** @var class-string<Model> $model */
        $model = CandidateCollectionRegistry::modelFor($slug);

        DB::transaction(function () use ($actor, $profile, $slug, $items, $model): void {
            // Serializes concurrent synchronizations of the same candidate.
            CandidateProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();

            $existing = $model::query()
                ->where('candidate_profile_id', $profile->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Model $row): int => (int) $row->getKey());

            // References must be resolved before the first collection mutation.
            $this->validateReferences($profile, $items);

            $created = 0;
            $updated = 0;
            $keptIds = [];

            foreach ($items as $item) {
                $id = isset($item['id']) ? (int) $item['id'] : null;
                $attributes = [];
                foreach ($this->fields() as $field) {
                    // PUT receives the complete desired collection. An omitted
                    // optional field is therefore cleared instead of retained.
                    $attributes[$field] = $item[$field] ?? null;
                }

                if ($id === null) {
                    $row = new $model();
                    $row->fill($attributes);
                    // Parent comes from the actor, never from the payload.
                    $row->candidate_profile_id = $profile->getKey();
                    $row->save();
                    $created++;
                    $keptIds[] = (int) $row->getKey();

                    continue;
                }

                $row = $existing->get($id);
                if (! $row instanceof Model) {
                    throw (new ModelNotFoundException())->setModel($model, [$id]);
                }

                $row->fill($attributes);
                if ($row->isDirty()) {
                    $row->updated_at = now();
                    $row->save();
                    $updated++;
                }
                $keptIds[] = $id;
            }

            $removed = 0;
            foreach ($existing as $id => $row) {
                if (! in_array($id, $keptIds, true)) {
                    $row->delete();
                    $removed++;
                }
            }

            // Counts only — row content is the candidate's own data (FR-AUD-001).
            if ($created !== 0 || $updated !== 0 || $removed !== 0) {
                $this->audit->record(
                    'candidate_profile_section_changed',
                    $actor,
                    'candidate_profile',
                    (int) $profile->getKey(),
                    ['collection' => $slug, 'created' => $created, 'updated' => $updated, 'removed' => $removed],
                );
            }
        });
    }

    /** @param list<array<string, mixed>> $items */
    protected function validateReferences(CandidateProfile $profile, array $items): void {}
}
