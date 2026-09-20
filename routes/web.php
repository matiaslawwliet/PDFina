<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;
use Native\Desktop\Facades\Shell;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('editar-pdf/pagina/{sesion}/{pagina}', function (string $sesion, string $pagina) {
        abort_unless(preg_match('/^[a-f0-9]{16}$/', $sesion) && preg_match('/^\d+$/', $pagina), 404);

        $ruta = storage_path('app/public/pdfina/temp/editar_'.$sesion.'/page-'.$pagina.'.png');
        abort_unless(is_file($ruta), 404);

        return response()->file($ruta, ['Cache-Control' => 'private, max-age=3600']);
    })->name('editar.pdf.pagina');
    Route::get('enviar-sugerencia', function () {
        Shell::openExternal('https://forms.gle/jH3rJ56ywuFaAgDB6');

        return redirect()->back();
    })->name('enviar.sugerencia');
    Route::get('reportar-problema', function () {
        Shell::openExternal('https://forms.gle/uwkr2JzwyeVbmh89A');

        return redirect()->back();
    })->name('reportar.problema');
});

require __DIR__.'/auth.php';
