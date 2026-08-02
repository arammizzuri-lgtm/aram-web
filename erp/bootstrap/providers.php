<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\MountServiceProvider;

return [
    AppServiceProvider::class,
    MountServiceProvider::class,
    AdminPanelProvider::class,
];
