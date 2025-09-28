<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Native\Laravel\Events\AutoUpdater\DownloadProgress;

class TrackUpdateProgress
{
    public function handle(DownloadProgress $event): void
    {
        Log::info('[Updater] Progreso de descarga.', [
            'percent' => $event->percent,
            'transferred' => $event->transferred,
            'total' => $event->total,
        ]);
    }
}
