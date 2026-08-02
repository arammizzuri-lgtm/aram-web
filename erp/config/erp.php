<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mount path
    |--------------------------------------------------------------------------
    |
    | The ERP is served from a path of the public site rather than from a host
    | of its own, and every route it registers carries this prefix so that the
    | URLs it generates are right whether they are absolute or root-relative.
    |
    | It must match the directory holding the front controller — public/erp at
    | the root of the repository — because that is what puts requests for these
    | URLs in front of this application in the first place. Renaming one without
    | the other leaves the ERP unreachable.
    |
    */

    'mount' => 'erp',

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | The ERP is deployed onto shared hosting with no shell access, so there is
    | no opportunity to run `make:filament-user` by hand after the first deploy.
    | `php artisan erp:provision`, which the deploy runs, creates that account
    | from these values instead.
    |
    | They are read from config rather than straight from env() on purpose: the
    | deploy caches the configuration, and env() returns null once it has.
    |
    */

    'admin' => [
        'name' => env('ERP_ADMIN_NAME', 'Owner'),
        'email' => env('ERP_ADMIN_EMAIL'),
        'password' => env('ERP_ADMIN_PASSWORD'),
    ],

];
