<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Support;

use App\Domains\Candidate\Exceptions\CandidateCollectionNotFoundException;
use App\Domains\Candidate\Models\CandidateCertification;
use App\Domains\Candidate\Models\CandidateEducation;
use App\Domains\Candidate\Models\CandidateLink;
use App\Domains\Candidate\Models\CandidateOrganization;
use App\Domains\Candidate\Models\CandidateSkill;
use App\Domains\Candidate\Models\CandidateWorkExperience;
use App\Http\Requests\Candidate\SyncCandidateCertificationsRequest;
use App\Http\Requests\Candidate\SyncCandidateEducationsRequest;
use App\Http\Requests\Candidate\SyncCandidateLinksRequest;
use App\Http\Requests\Candidate\SyncCandidateOrganizationsRequest;
use App\Http\Requests\Candidate\SyncCandidateSkillsRequest;
use App\Http\Requests\Candidate\SyncCandidateWorkExperiencesRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * The six repeatable profile collections of the grouped contract
 * `PUT /api/v1/candidate/{collection}`, declared once.
 */
final class CandidateCollectionRegistry
{
    /** @var array<string, array{model: class-string<Model>, request: class-string, order: list<string>}> */
    private const COLLECTIONS = [
        'educations' => [
            'model' => CandidateEducation::class,
            'request' => SyncCandidateEducationsRequest::class,
            'order' => ['graduation_date', 'id'],
        ],
        'work-experiences' => [
            'model' => CandidateWorkExperience::class,
            'request' => SyncCandidateWorkExperiencesRequest::class,
            'order' => ['start_date', 'id'],
        ],
        'organizations' => [
            'model' => CandidateOrganization::class,
            'request' => SyncCandidateOrganizationsRequest::class,
            'order' => ['start_date', 'id'],
        ],
        'certifications' => [
            'model' => CandidateCertification::class,
            'request' => SyncCandidateCertificationsRequest::class,
            'order' => ['issued_at', 'id'],
        ],
        'links' => [
            'model' => CandidateLink::class,
            'request' => SyncCandidateLinksRequest::class,
            'order' => ['sort_order', 'id'],
        ],
        'skills' => [
            'model' => CandidateSkill::class,
            'request' => SyncCandidateSkillsRequest::class,
            'order' => ['id'],
        ],
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::COLLECTIONS);
    }

    /** @return class-string<Model> @throws CandidateCollectionNotFoundException */
    public static function modelFor(string $slug): string
    {
        return self::definition($slug)['model'];
    }

    /** @return class-string @throws CandidateCollectionNotFoundException */
    public static function requestFor(string $slug): string
    {
        return self::definition($slug)['request'];
    }

    /** @return list<string> @throws CandidateCollectionNotFoundException */
    public static function orderFor(string $slug): array
    {
        return self::definition($slug)['order'];
    }

    /** @return array{model: class-string<Model>, request: class-string, order: list<string>} */
    private static function definition(string $slug): array
    {
        return self::COLLECTIONS[$slug] ?? throw new CandidateCollectionNotFoundException($slug);
    }
}
