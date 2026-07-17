<?php

return [
    'product_name' => env('SUPERSOFT_PRODUCT_NAME', 'SuperSoft Enterprise'),
    'company_name' => env('SUPERSOFT_COMPANY_NAME', 'PT Supersoft Global Investama'),
    'domain' => env('SUPERSOFT_DOMAIN', 'supersoft.id'),
    'version' => env('SUPERSOFT_VERSION', '1.0.1'),
    'backup_encryption_key' => env('SUPERSOFT_BACKUP_ENCRYPTION_KEY'),
    'developer' => 'Rendy Mandolang, SE., MM., CPA., CHCGM.',
    'contact' => [
        'primary' => 'hello@supersoft.id',
        'investor' => 'rendymandolang@gmail.com',
    ],
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'SuperSoft Administrator'),
        'email' => env('INITIAL_ADMIN_EMAIL', 'admin@supersoft.local'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
