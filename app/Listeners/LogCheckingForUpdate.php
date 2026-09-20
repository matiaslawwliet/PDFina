<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;

class LogCheckingForUpdate
{
    public function handle(CheckingForUpdate $event): void
    {
        Log::info('[Updater] Se activó la búsqueda de actualización.');
    }
}
