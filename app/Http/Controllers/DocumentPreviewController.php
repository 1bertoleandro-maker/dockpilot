<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPreviewController extends Controller
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        abort_unless($request->user() !== null, 401);

        $companyId = CurrentCompany::check();

        abort_unless((int) $document->company_id === $companyId, 403);

        $disk = Storage::disk((string) config('ai.disk', 'local'));

        abort_unless($disk->exists($document->storage_path), 404);

        return $disk->response(
            $document->storage_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
            ]
        );
    }
}
