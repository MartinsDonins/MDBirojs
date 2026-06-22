<?php

namespace App\Http\Controllers;

use App\Models\GidDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves stored EDS documents (GID XML/HTML/PDF, IIN XML). HTML/PDF open inline so the
 * user can read the printed declaration; XML downloads. Auth-protected via the route.
 */
class GidDocumentController extends Controller
{
    public function show(GidDocument $document): StreamedResponse|Response
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response(
            $document->path,
            $document->filename,
            ['Content-Type' => $document->mime ?: 'application/octet-stream'],
            $document->opensInline() ? 'inline' : 'attachment',
        );
    }
}
