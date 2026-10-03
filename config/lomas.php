<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node Identity
    |--------------------------------------------------------------------------
    |
    | Every installation is a node. The code is written into every business
    | number and every audit entry, so two offline nodes never collide and no
    | central allocator is needed. It is set once, here, and never edited.
    |
    | See AGENTS.md section 69.2.
    |
    */

    'node' => [
        'code' => env('LOMAS_NODE_CODE', 'MNL1'),
        'name' => env('LOMAS_NODE_NAME', 'Manila Node'),
        'role' => env('LOMAS_NODE_ROLE', 'branch'),
        'timezone' => env('LOMAS_NODE_TIMEZONE', 'Asia/Manila'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Money Formatting
    |--------------------------------------------------------------------------
    |
    | The locale used when amounts are formatted for display, printing and
    | exports. The currency itself comes from the system settings.
    |
    */

    'money_locale' => env('LOMAS_MONEY_LOCALE', 'en_PH'),

    /*
    |--------------------------------------------------------------------------
    | Initial Administrator
    |--------------------------------------------------------------------------
    |
    | Credentials used by the seeder to create the first administrator so the
    | installation can be logged into. Change the password after the first
    | sign in.
    |
    */

    'admin' => [
        'name' => env('LOMAS_ADMIN_NAME', 'Administrator'),
        'email' => env('LOMAS_ADMIN_EMAIL', 'admin@lomas.test'),
        'password' => env('LOMAS_ADMIN_PASSWORD', 'password'),
    ],

];
