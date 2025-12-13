<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;

class LogCheckingForUpdate
{
    public function handle(CheckingForUpdate $event): void
    {
        Log::info('[Updater] Checking for update triggered.');
    }
}
