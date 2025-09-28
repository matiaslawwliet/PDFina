<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;
use Native\Laravel\Facades\Shell;

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
    Route::get('enviar-sugerencia', function () {
        $url = 'https://forms.gle/jH3rJ56ywuFaAgDB6';
        try {
            Shell::openExternal($url);
        } catch (\Exception $e) {
            //echo $e->getMessage();
        }
        return redirect()->back();
    })->name('enviar.sugerencia');
    Route::get('reportar-problema', function () {
        $url = 'https://forms.gle/uwkr2JzwyeVbmh89A';
        try {
            Shell::openExternal($url);
        } catch (\Exception $e) {
            //echo $e->getMessage();
        }
        return redirect()->back();
    })->name('reportar.problema');
});

require __DIR__.'/auth.php';
