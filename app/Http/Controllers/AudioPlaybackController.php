<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioPlaybackController extends Controller
{
    /**
     * Stream the merged audio file with HTTP Range support and signed URL verification.
     */
    public function stream(Request $request, Meeting $meeting): BinaryFileResponse
    {
        // Enforce authorization via RawMaterialPolicy (creator notulis & pimpinan only; admin forbidden)
        $this->authorize('viewAudio', $meeting);

        $disk = config('recording.disk', 'local');
        $storage = Storage::disk($disk);
        $relativePath = $meeting->merged_audio_path;

        if (empty($relativePath) || ! $storage->exists($relativePath)) {
            abort(404, 'Berkas audio rapat tidak ditemukan.');
        }

        $fullPath = $storage->path($relativePath);

        // Record audit log on playback start (no Range header or Range beginning at byte 0)
        $range = $request->header('Range');
        if (empty($range) || str_starts_with($range, 'bytes=0-')) {
            Audit::record('audio_play', $meeting->id);
        }

        // Return inline binary file response with HTTP Range support
        $response = new BinaryFileResponse($fullPath, 200, [
            'Content-Type' => 'audio/mp4',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ], false);

        $response->prepare($request);

        return $response;
    }
}
