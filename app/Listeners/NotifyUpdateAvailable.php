<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Native\Laravel\Events\AutoUpdater\UpdateAvailable;
use Native\Laravel\Facades\Alert;

class NotifyUpdateAvailable
{
    public function handle(UpdateAvailable $event): void
    {
        Log::info('[Updater] Update disponible detectada.', [
            'version' => $event->version,
        ]);

        Alert::new()
            ->type('info')
            ->title('Actualización disponible')
            ->detail('La descarga se iniciará automáticamente en segundo plano.')
            ->show("Nueva versión disponible ({$event->version}).");
    }
}
