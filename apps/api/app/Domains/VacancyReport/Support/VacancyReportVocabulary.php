<?php

declare(strict_types=1);

namespace App\Domains\VacancyReport\Support;

/** Closed MVP vocabularies for "Laporkan Lowongan" (PGC-V1 / PD-C). */
final class VacancyReportVocabulary
{
    public const MAX_DETAILS = 2000;

    /** @var array<string, string> reason code => Indonesian label */
    public const REASONS = [
        'FRAUD_OR_SCAM' => 'Dugaan penipuan',
        'MISLEADING_INFORMATION' => 'Informasi menyesatkan',
        'INAPPROPRIATE_CONTENT' => 'Konten tidak pantas',
        'INVALID_OR_EXPIRED_VACANCY' => 'Lowongan tidak valid/kedaluwarsa',
        'SUSPICIOUS_EXTERNAL_LINK' => 'Tautan eksternal mencurigakan',
        'OTHER' => 'Lainnya',
    ];

    public const STATUS_NEW = 'NEW';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_ACTIONED = 'ACTIONED';
    public const STATUS_DISMISSED = 'DISMISSED';

    /** @return list<string> */
    public static function reasons(): array
    {
        return array_keys(self::REASONS);
    }

    public static function isReason(string $reason): bool
    {
        return array_key_exists($reason, self::REASONS);
    }

    /** @return list<array{code: string, label: string}> */
    public static function reasonOptions(): array
    {
        $out = [];
        foreach (self::REASONS as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }
}
