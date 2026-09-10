<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Actions\DownloadApplicationDocument;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /application-documents/{applicationDocument}/download` (PGC-V1 / PD-A).
 * Session-guarded. All authorization, auditing and the enumeration-safe 404
 * live in `DownloadApplicationDocument`.
 */
final class ApplicationDocumentController extends Controller
{
    public function download(Request $request, int $applicationDocument, DownloadApplicationDocument $action): StreamedResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $action->execute($actor, $applicationDocument);
    }
}
