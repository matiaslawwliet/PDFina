<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\Menu\CheckUpdateClicked::class => [
            \App\Listeners\RunSearchUpdates::class,
        ],

        \Native\Desktop\Events\AutoUpdater\CheckingForUpdate::class => [
            \App\Listeners\LogCheckingForUpdate::class,
        ],

        \Native\Desktop\Events\AutoUpdater\UpdateAvailable::class => [
            \App\Listeners\NotifyUpdateAvailable::class,
        ],

        \Native\Desktop\Events\AutoUpdater\UpdateNotAvailable::class => [
            \App\Listeners\NotifyNoUpdate::class,
        ],

        \Native\Desktop\Events\AutoUpdater\DownloadProgress::class => [
            \App\Listeners\TrackUpdateProgress::class,
        ],

        \Native\Desktop\Events\AutoUpdater\UpdateDownloaded::class => [
            \App\Listeners\NotifyUpdateDownloaded::class,
        ],

        \Native\Desktop\Events\AutoUpdater\Error::class => [
            \App\Listeners\HandleUpdateError::class,
        ],
    ];
}
