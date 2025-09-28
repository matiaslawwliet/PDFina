<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\Menu\CheckUpdateClicked::class => [
            \App\Listeners\RunSearchUpdates::class,
        ],

        \Native\Laravel\Events\AutoUpdater\CheckingForUpdate::class => [
            \App\Listeners\LogCheckingForUpdate::class,
        ],

        \Native\Laravel\Events\AutoUpdater\UpdateAvailable::class => [
            \App\Listeners\NotifyUpdateAvailable::class,
        ],

        \Native\Laravel\Events\AutoUpdater\UpdateNotAvailable::class => [
            \App\Listeners\NotifyNoUpdate::class,
        ],

        \Native\Laravel\Events\AutoUpdater\DownloadProgress::class => [
            \App\Listeners\TrackUpdateProgress::class,
        ],

        \Native\Laravel\Events\AutoUpdater\UpdateDownloaded::class => [
            \App\Listeners\NotifyUpdateDownloaded::class,
        ],

        \Native\Laravel\Events\AutoUpdater\Error::class => [
            \App\Listeners\HandleUpdateError::class,
        ],
    ];
}
