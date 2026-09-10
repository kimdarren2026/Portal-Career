<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Domains\Notification\Support\EmailTemplateCatalog;
use Tests\TestCase;

/**
 * PGC-V1 / PD-B coverage check.
 *
 *  - Every reference the catalog claims to know renders a non-empty subject
 *    and body in the standard layout.
 *  - Every `template_reference` the production runtime actually emits (the
 *    full enumerated list, static + dynamically assembled) is catalogued.
 *
 * `OutboxWriter::queue()` additionally rejects an unknown reference at write
 * time, so the whole feature suite is a live third check.
 */
final class EmailTemplateCoverageTest extends TestCase
{
    /** Every `template_reference` a production notifier can emit. */
    private const RUNTIME_REFERENCES = [
        // Identity
        'identity.email-verification', 'identity.account-already-registered',
        'identity.password-reset', 'identity.password-reset-completed', 'identity.password-changed',
        'identity.role.assigned', 'identity.role.revoked',
        // Company
        'company.member.invited',
        'company.verification.SUBMITTED', 'company.verification.VERIFY', 'company.verification.REQUEST_REVISION',
        'company.verification.REJECT', 'company.verification.SUSPEND', 'company.verification.RESTORE',
        // Vacancy lifecycle
        'vacancy.lifecycle.submit', 'vacancy.lifecycle.approve', 'vacancy.lifecycle.request_revision',
        'vacancy.lifecycle.reject', 'vacancy.lifecycle.publish', 'vacancy.lifecycle.close',
        'vacancy.lifecycle.suspend', 'vacancy.lifecycle.restore', 'vacancy.moderation.queue',
        // Application
        'application.submitted.candidate', 'application.submitted.owner',
        'application.withdrawn.candidate', 'application.withdrawn.owner',
        'application.transitioned.candidate', 'application.stage_moved.candidate',
        // Selection schedule
        'schedule.created.candidate', 'schedule.created.pic',
        'schedule.rescheduled.candidate', 'schedule.rescheduled.pic',
        'schedule.cancelled.candidate', 'schedule.cancelled.pic',
        // Evaluation
        'evaluation.submitted.owner',
        // Offering
        'offer.sent.candidate', 'offer.accepted.candidate', 'offer.accepted.owner', 'offer.rejected.owner',
        // Selector assignment
        'selection.selector.assignment.assigned', 'selection.selector.assignment.revoked',
    ];

    public function test_every_catalogued_reference_renders_in_the_standard_layout(): void
    {
        $catalog = new EmailTemplateCatalog();
        $refs = EmailTemplateCatalog::references();
        self::assertNotEmpty($refs);

        foreach ($refs as $ref) {
            $rendered = $catalog->render($ref, [
                'role_code' => 'AUDITOR', 'company_role' => 'COMPANY_RECRUITER',
                'vacancy_id' => 1, 'status' => 'PUBLISHED', 'application_id' => 2,
                'schedule_id' => 3, 'method' => 'ONLINE', 'recruitment_stage_id' => 4,
            ]);
            self::assertNotSame('', trim($rendered['subject']), $ref);
            self::assertNotSame('', trim($rendered['body']), $ref);
            self::assertStringContainsString('STIKES Advaita Medika Tabanan', $rendered['body'], $ref);
            self::assertStringContainsString('dikirim otomatis oleh Portal Karir', $rendered['body'], $ref);
        }
    }

    public function test_every_runtime_reference_is_catalogued(): void
    {
        $missing = array_values(array_filter(
            self::RUNTIME_REFERENCES,
            static fn (string $ref): bool => ! EmailTemplateCatalog::has($ref),
        ));

        self::assertSame([], $missing, 'Uncatalogued runtime template references: '.implode(', ', $missing));
    }

    public function test_secrets_never_appear_in_a_rendered_body(): void
    {
        $catalog = new EmailTemplateCatalog();
        foreach (EmailTemplateCatalog::references() as $ref) {
            $body = $catalog->render($ref, ['password' => 'hunter2', 'token' => 'raw-token-xyz', 'secret' => 's3cr3t'])['body'];
            self::assertStringNotContainsString('hunter2', $body, $ref);
            self::assertStringNotContainsString('raw-token-xyz', $body, $ref);
            self::assertStringNotContainsString('s3cr3t', $body, $ref);
        }
    }
}
