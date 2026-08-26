<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Company\Queries\GetPublicCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/public/companies/{slug} (PD-2) — VERSIONED_API, unauthenticated,
 * PUBLIC. Single-company summary only; never a directory (§25 of the
 * readiness audit — Public Company Directory remains a separate future phase).
 */
final class PublicCompanyController extends Controller
{
    public function show(Request $request, string $slug, GetPublicCompany $query): JsonResponse
    {
        // CompanyNotPublic (nonexistent slug or a non-VERIFIED company) is
        // handled by the global exception mapping — never caught here.
        return ContractResponse::success($request, $query->execute($slug));
    }
}
