<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /vacancies/{vacancy}/applications` transport-level shape only.
 * Every business rule (eligibility, consent version, receiver, document
 * ownership, screening validity) is enforced inside `SubmitApplication` —
 * this class only rejects a malformed request before that transaction opens.
 */
final class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['sometimes', 'array'],
            'document_ids.*' => ['integer'],
            'screening_answers' => ['sometimes', 'array'],
            'screening_answers.*.screening_question_id' => ['required_with:screening_answers', 'integer'],
            // Type-agnostic on purpose: the answer's expected shape (text,
            // boolean, number, or a fixed choice) depends on the question it
            // targets, which is not known until `SubmitApplication` resolves
            // it against this vacancy's active questions.
            'screening_answers.*.answer_value' => ['sometimes', 'nullable'],
            'consent' => ['required', 'array'],
            'consent.consent_version' => ['required', 'string'],
            'consent.consent_text_hash_reference' => ['required', 'string'],
            'consent.accepted' => ['required', 'boolean'],
            // The receiver is always server-derived (INV-023); a client that
            // supplies either field at all is a mismatch, not a silent ignore.
            'receiving_company_id' => ['sometimes'],
            'receiving_organizational_unit_id' => ['sometimes'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $documentIds = $this->input('document_ids', []);
            if (is_array($documentIds) && count($documentIds) !== count(array_unique($documentIds))) {
                $validator->errors()->add('document_ids', 'Dokumen tidak boleh duplikat.');
            }

            $answers = $this->input('screening_answers', []);
            if (is_array($answers)) {
                $seen = [];
                foreach ($answers as $answer) {
                    $qid = is_array($answer) ? ($answer['screening_question_id'] ?? null) : null;
                    if ($qid === null) {
                        continue;
                    }
                    if (isset($seen[$qid])) {
                        $validator->errors()->add('screening_answers', 'Jawaban seleksi tidak boleh duplikat untuk pertanyaan yang sama.');
                    }
                    $seen[$qid] = true;
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ContractResponse::error(
            $this,
            'VALIDATION_FAILED',
            422,
            'Data yang dikirim tidak valid.',
            ['fields' => $validator->errors()->toArray()],
        ));
    }
}
