<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\Error;
use Native\Desktop\Facades\Alert;

class HandleUpdateError
{
    public function handle(Error $event): void
    {
        $message = $event->message ?? 'Error desconocido durante la actualización.';

        Log::error('[Updater] Error event recibido.', [
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
