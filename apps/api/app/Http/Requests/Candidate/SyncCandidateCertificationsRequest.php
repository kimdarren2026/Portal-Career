<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

final class SyncCandidateCertificationsRequest extends CandidateCollectionRequest
{
    protected function itemFields(): array { return ['id', 'certification_name', 'issuer_name', 'credential_identifier', 'issued_at', 'expires_at', 'credential_url', 'document_id']; }
    protected function itemRules(): array { return ['items.*.certification_name' => ['required', 'string', 'max:255'], 'items.*.issuer_name' => ['required', 'string', 'max:255'], 'items.*.credential_identifier' => ['nullable', 'string', 'max:255'], 'items.*.issued_at' => ['nullable', 'date_format:Y-m-d'], 'items.*.expires_at' => ['nullable', 'date_format:Y-m-d'], 'items.*.credential_url' => ['nullable', 'url:http,https', 'max:2048'], 'items.*.document_id' => ['nullable', 'integer']]; }

    protected function validateSemantics(Validator $validator): void { foreach ((array) $this->input('items', []) as $index => $item) { if (is_array($item) && ! empty($item['issued_at']) && ! empty($item['expires_at']) && $item['expires_at'] < $item['issued_at']) { $validator->errors()->add("items.$index.expires_at", 'Tanggal berakhir tidak boleh sebelum tanggal terbit.'); } } }
}
