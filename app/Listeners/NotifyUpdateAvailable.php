<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Facades\Alert;

class NotifyUpdateAvailable
{
    public function handle(UpdateAvailable $event): void
    {
        // Limpiar flag de búsqueda manual ya que mostraremos alerta de todas formas
        Cache::forget('updater_manual_check');

        Log::info('[Updater] Actualización disponible detectada.', [
            'version' => $event->version,
        ]);

        Alert::new()
            ->type('info')
            ->title('Actualización disponible')
            ->detail('La descarga se iniciará automáticamente en segundo plano.')
            ->show("Nueva versión disponible ({$event->version}).");
    }
}
