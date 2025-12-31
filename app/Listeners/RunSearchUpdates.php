<?php

namespace App\Listeners;

use App\Events\Menu\CheckUpdateClicked;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\AutoUpdater;

class RunSearchUpdates
{
    public function handle(CheckUpdateClicked $event): void
    {
        // Marcar que esta búsqueda fue iniciada manualmente por el usuario
        Cache::put('updater_manual_check', true, now()->addMinutes(5));

        Log::info('[Updater] Búsqueda manual de actualizaciones iniciada por el usuario.');

        AutoUpdater::checkForUpdates();
    }
}
