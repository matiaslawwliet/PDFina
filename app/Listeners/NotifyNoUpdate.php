<?php

namespace App\Listeners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;
use Native\Desktop\Facades\Alert;

class NotifyNoUpdate
{
    public function handle(UpdateNotAvailable $event): void
    {
        Log::info('[Updater] No hay actualizaciones disponibles.');

        Alert::new()
            ->type('info')
            ->title('Sin actualizaciones')
            ->show('Tu aplicación ya está actualizada a la última versión.');

        $updaterPath = rtrim(getenv('LOCALAPPDATA') ?: '', '\\/') . DIRECTORY_SEPARATOR . 'pdfina-updater';

        if ($updaterPath && File::exists($updaterPath)) {
            if (File::deleteDirectory($updaterPath, true)) {
                Log::info('[Updater] Contenido residual de pdfina-updater eliminado tras no detectar actualizaciones.', [
                    'path' => $updaterPath,
                ]);
            } else {
                Log::warning('[Updater] No se pudo eliminar el contenido residual de pdfina-updater tras no detectar actualizaciones.', [
                    'path' => $updaterPath,
                ]);
            }
        } else {
            Log::info('[Updater] Carpeta pdfina-updater no encontrada o variable LOCALAPPDATA no definida al no detectar actualizaciones.', [
                'path' => $updaterPath,
            ]);
        }
    }
}
