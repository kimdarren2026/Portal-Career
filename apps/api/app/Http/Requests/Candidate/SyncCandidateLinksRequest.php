<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class SyncCandidateLinksRequest extends CandidateCollectionRequest
{
    private const TYPES = ['LINKEDIN', 'PORTFOLIO', 'PERSONAL_WEBSITE', 'PUBLICATION', 'OTHER'];
    protected function itemFields(): array { return ['id', 'link_type', 'label', 'url', 'sort_order']; }
    protected function itemRules(): array { return ['items.*.link_type' => ['required', 'string', Rule::in(self::TYPES)], 'items.*.label' => ['nullable', 'string', 'max:255'], 'items.*.url' => ['required', 'url:http,https', 'max:2048'], 'items.*.sort_order' => ['required', 'integer']]; }
    protected function validateSemantics(Validator $validator): void { $urls = []; foreach ((array) $this->input('items', []) as $index => $item) { if (! is_array($item) || ! isset($item['url'])) { continue; } if (isset($urls[$item['url']])) { $validator->errors()->add("items.$index.url", 'URL tidak boleh duplikat.'); } $urls[$item['url']] = true; } }
}
