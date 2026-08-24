<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

abstract class CandidateCollectionRequest extends CandidateFormRequest
{
    /** @return array<string, list<mixed>> */
    abstract protected function itemRules(): array;

    /** @return list<string> */
    abstract protected function itemFields(): array;

    public function rules(): array
    {
        return array_merge([
            // `[]` is a deliberate full replacement that clears a section.
            'items' => ['present', 'array'],
            'items.*' => ['array'],
            'items.*.id' => ['sometimes', 'integer'],
        ], $this->itemRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if ($field !== 'items') {
                    $validator->errors()->add($field, 'Field tidak didukung.');
                }
            }

            $seenIds = [];
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (array_keys($item) as $field) {
                    if (! in_array($field, $this->itemFields(), true)) {
                        $validator->errors()->add("items.$index.$field", 'Field tidak didukung.');
                    }
                }
                if (array_key_exists('id', $item)) {
                    $id = (string) $item['id'];
                    if (isset($seenIds[$id])) {
                        $validator->errors()->add("items.$index.id", 'ID tidak boleh duplikat.');
                    }
                    $seenIds[$id] = true;
                }
            }

            $this->validateSemantics($validator);
        });
    }

    protected function validateSemantics(Validator $validator): void {}

    protected function validateTimeline(Validator $validator, int|string $index): void
    {
        $item = $this->input("items.$index", []);
        if (! is_array($item)) {
            return;
        }

        if (($item['is_current'] ?? false) === true && ! empty($item['end_date'])) {
            $validator->errors()->add("items.$index.end_date", 'Tanggal selesai harus kosong untuk posisi yang masih berlangsung.');
        }
        if (! empty($item['start_date']) && ! empty($item['end_date']) && $item['end_date'] < $item['start_date']) {
            $validator->errors()->add("items.$index.end_date", 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }
    }
}
