<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shared envelope for the frozen browser HTTP contract. */
final class ContractResponse
{
    /** @param array<string, mixed> $data @param list<string> $warnings */
    public static function success(Request $request, array $data, int $status = 200, array $warnings = []): JsonResponse
    {
        $payload = ['data' => $data];
        if ($warnings !== []) {
            $payload['meta'] = [
                'correlation_id' => $request->attributes->get('correlation_id'),
                'warnings' => $warnings,
            ];
        }

        return response()->json($payload, $status);
    }

    /** @param array<string, mixed> $details */
    public static function error(
        Request $request,
        string $code,
        int $status,
        string $message,
        array $details = [],
        ?int $retryAfter = null,
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'correlation_id' => $request->attributes->get('correlation_id'),
        ];
        if ($details !== []) {
            $error['details'] = $details;
        }

        $response = response()->json(['error' => $error], $status);
        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) max(1, $retryAfter));
        }

        return $response;
    }
}
