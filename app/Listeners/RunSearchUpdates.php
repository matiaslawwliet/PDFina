<?php

namespace App\Listeners;

use App\Events\Menu\CheckUpdateClicked;
use Illuminate\Support\Facades\Log;
use Native\Laravel\Facades\Alert;
use Native\Laravel\Facades\AutoUpdater;

class RunSearchUpdates
{
    public function handle(CheckUpdateClicked $event): void
    {
        Alert::new()
            ->type('info')
            ->title('Actualizaciones')
            ->show('Buscando actualizaciones...');

        Log::info('[Updater] Comenzando la búsqueda de actualizaciones.');

        AutoUpdater::checkForUpdates();
    }
}
