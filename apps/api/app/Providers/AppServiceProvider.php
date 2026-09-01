<?php

namespace App\Providers;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Policies\CandidateDocumentPolicy;
use App\Domains\Candidate\Policies\CandidateProfilePolicy;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Policies\CompanyPolicy;
use App\Domains\Notification\Models\SmtpConfiguration;
use App\Domains\Notification\Policies\SmtpConfigurationPolicy;
use App\Domains\Notification\Support\SmtpTestSender;
use App\Domains\Notification\Support\SymfonyMailerSmtpTestSender;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Policies\VacancyPolicy;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmtpTestSender::class, SymfonyMailerSmtpTestSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Gate $gate): void
    {
        $gate->policy(CandidateProfile::class, CandidateProfilePolicy::class);
        $gate->policy(CandidateDocument::class, CandidateDocumentPolicy::class);
        $gate->policy(Company::class, CompanyPolicy::class);
        $gate->policy(Vacancy::class, VacancyPolicy::class);
        $gate->policy(SmtpConfiguration::class, SmtpConfigurationPolicy::class);
    }
}
