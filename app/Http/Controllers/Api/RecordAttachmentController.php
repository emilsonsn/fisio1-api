<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecordAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class RecordAttachmentController extends Controller
{
    public function download(RecordAttachment $recordAttachment)
    {
        abort_unless(Storage::disk($recordAttachment->disk)->exists($recordAttachment->path), 404);

        return Storage::disk($recordAttachment->disk)->download($recordAttachment->path, $recordAttachment->original_name);
    }

    public function destroy(Request $request, RecordAttachment $recordAttachment): Response
    {
        $record = $recordAttachment->attachable;
        abort_unless($record && ($request->user()->hasPermission('clinical_records.manage_all') || $record->professional_id === $request->user()->id), 403);
        Storage::disk($recordAttachment->disk)->delete($recordAttachment->path);
        $recordAttachment->delete();

        return response()->noContent();
    }
}
