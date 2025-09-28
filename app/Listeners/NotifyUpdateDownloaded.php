<?php

namespace App\Listeners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Native\Laravel\Events\AutoUpdater\UpdateDownloaded;
use Native\Laravel\Facades\Alert;
use Native\Laravel\Facades\App as NativeApp;
use Native\Laravel\Facades\Shell;

class NotifyUpdateDownloaded
{
    public function handle(UpdateDownloaded $event): void
    {
        Log::info('[Updater] Descarga de actualización completada.', [
            'version' => $event->version,
            'file' => $event->downloadedFile,
        ]);

        $choice = Alert::new()
            ->type('info')
            ->title('Actualización descargada')
            ->detail('Se necesita cerrar PDFina para aplicar la actualización.')
            ->buttons(['No instalar ahora', 'Instalar ahora'])
            ->defaultId(1)
            ->cancelId(0)
            ->show('La actualización se descargó correctamente. ¿Quieres reiniciar ahora para instalarla?');

        if ($choice === 1) {
            $result = Shell::openFile($event->downloadedFile);

            Log::info('[Updater] Intentando abrir instalador.', [
                'result' => $result,
            ]);

            NativeApp::quit();
            return;
        }

        Log::info('[Updater] Instalación pospuesta. Eliminando instalador descargado.');

        try {
            Shell::trashFile($event->downloadedFile);
        } catch (\Throwable $exception) {
            Log::warning('[Updater] No se pudo mover el instalador a la papelera.', [
                'file' => $event->downloadedFile,
                'exception' => $exception->getMessage(),
            ]);
        }

        $updaterPath = rtrim(getenv('LOCALAPPDATA') ?: '', '\\/') . DIRECTORY_SEPARATOR . 'pdfina-updater';

        if ($updaterPath && File::exists($updaterPath)) {
            if (File::deleteDirectory($updaterPath, true)) {
                Log::info('[Updater] Contenido de pdfina-updater eliminado satisfactoriamente.', [
                    'path' => $updaterPath,
                ]);
            } else {
                Log::warning('[Updater] No se pudo eliminar el contenido de pdfina-updater.', [
                    'path' => $updaterPath,
                ]);
            }
        } else {
            Log::info('[Updater] Carpeta pdfina-updater no encontrada o variable LOCALAPPDATA no definida.', [
                'path' => $updaterPath,
            ]);
        }
    }
}
