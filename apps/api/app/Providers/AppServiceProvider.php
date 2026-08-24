<?php

namespace App\Providers;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Policies\CandidateDocumentPolicy;
use App\Domains\Candidate\Policies\CandidateProfilePolicy;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Gate $gate): void
    {
        $gate->policy(CandidateProfile::class, CandidateProfilePolicy::class);
        $gate->policy(CandidateDocument::class, CandidateDocumentPolicy::class);
    }
}
