<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\Error;
use Native\Desktop\Facades\Alert;

class HandleUpdateError
{
    public function handle(Error $event): void
    {
        Cache::forget('updater_manual_check');

        $message = $event->message ?? 'Error desconocido durante la actualización.';

        Log::error('[Updater] Evento de error recibido.', [
            'name' => $event->name,
            'message' => $message,
            'stack' => $event->stack,
        ]);

        Alert::new()
            ->type('error')
            ->title('Error al actualizar')
            ->detail($event->name)
            ->show($message);
    }
}
