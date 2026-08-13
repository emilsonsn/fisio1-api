<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalAi\ProcessClinicalAudioRequest;
use App\Models\ClinicalAiDraft;
use App\Services\Gemini\GeminiClinicalAudioService;
use Illuminate\Support\Facades\Storage;

class ClinicalAiController extends Controller
{
    public function processAudio(ProcessClinicalAudioRequest $request, GeminiClinicalAudioService $gemini): array
    {
        $audio = $request->file('audio');
        $path = $audio->store('clinical-ai/audio', 'local');
        try {
            $processed = $gemini->process($audio, $request->string('type')->toString());
        } finally {
            Storage::disk('local')->delete($path);
        }
        $draft = ClinicalAiDraft::create(['patient_id' => $request->integer('patient_id'), 'professional_id' => $request->user()->id, 'type' => $request->string('type')->toString(), 'performed_at' => $request->date('performed_at'), 'transcript' => $processed['transcript'], 'fields' => $processed['fields'], 'processed_at' => now()]);

        return ['data' => ['id' => $draft->id, 'patient_id' => $draft->patient_id, 'type' => $draft->type, 'performed_at' => $draft->performed_at->toDateString(), 'transcript' => $draft->transcript, 'fields' => $draft->fields]];
    }
}
