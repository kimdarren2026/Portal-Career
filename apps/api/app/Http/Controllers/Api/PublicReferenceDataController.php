<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\MasterData\Queries\GetPublicReferenceData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/public/reference-data — VERSIONED_API, unauthenticated, PUBLIC. */
final class PublicReferenceDataController extends Controller
{
    public function index(Request $request, GetPublicReferenceData $query): JsonResponse
    {
        return ContractResponse::success($request, $query->execute());
    }
}
