<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Domains\Candidate\Support\CandidatePresenter;

/** GET /candidate/profile — attributes, verification summary, sub-collection counts. */
final class GetCandidateProfile
{
    /** Relation name per collection slug. */
    private const RELATIONS = [
        'educations' => 'educations',
        'work-experiences' => 'workExperiences',
        'organizations' => 'organizations',
        'certifications' => 'certifications',
        'links' => 'links',
        'skills' => 'skills',
    ];

    public function __construct(private readonly GetCandidateCollection $collections) {}

    /**
     * @param  list<string>  $include  Allow-listed slugs to embed inline.
     * @return array<string, mixed>
     */
    public function execute(CandidateProfile $profile, array $include = []): array
    {
        $payload = CandidatePresenter::profile($profile);

        $payload['verifications'] = $profile->verifications()
            ->orderBy('verification_type')->orderBy('id')
            ->get()->map(CandidatePresenter::verification(...))->all();

        $counts = [];
        $embedded = [];
        foreach (self::RELATIONS as $slug => $relation) {
            $counts[$slug] = $profile->{$relation}()->count();
            if (in_array($slug, $include, true)) {
                $embedded[$slug] = $this->collections->execute($profile, $slug);
            }
        }

        $payload['collections'] = [
            'counts' => $counts,
            'embedded' => $embedded,
        ];

        return $payload;
    }

    /** @return list<string> */
    public static function includableSlugs(): array
    {
        return CandidateCollectionRegistry::slugs();
    }
}
