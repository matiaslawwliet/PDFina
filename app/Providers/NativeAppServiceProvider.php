<?php

namespace App\Providers;

use Native\Laravel\Facades\Menu;
use Native\Laravel\Facades\Window;
use Native\Laravel\Contracts\ProvidesPhpIni;
use App\Events\Menu\CheckUpdateClicked;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Menu::create(
            Menu::make(
                Menu::quit('Salir de '.config('app.name')),
            )->label('Archivo'),
            Menu::make(
                Menu::undo('Deshacer'),
                Menu::redo('Rehacer'),
                Menu::separator(),
                Menu::cut('Cortar'),
                Menu::copy('Copiar'),
                Menu::paste('Pegar'),
            )->label('Edición'),
            Menu::make(
                Menu::reload('Recargar'),
                Menu::separator(),
                Menu::fullscreen('Pantalla completa'),
                Menu::devTools('Herramientas de desarrollo'),
            )->label('Vista'),
            Menu::make(
                Menu::minimize('Minimizar'),
            )->label('Ventana'),
            Menu::make(
                Menu::label('Buscar actualizaciones')
                    ->event(CheckUpdateClicked::class)
            )->label('Actualizar'),
        );

        Window::open()
            ->maximized();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'upload_max_filesize' => '1024M',
            'post_max_size' => '1024M',
            'memory_limit' => '1024M',
            'display_errors' => '1',
            'error_reporting' => 'E_ALL',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}
