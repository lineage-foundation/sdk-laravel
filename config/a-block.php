<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ABlock endpoints
    |--------------------------------------------------------------------------
    |
    */

    'proxy_url' => env('A_BLOCK_PROXY_URL'),
    'compute_host' => env('A_BLOCK_COMPUTE_HOST'),
    'storage_host' => env('A_BLOCK_STORAGE_HOST'),
    'notary_host' => env('A_BLOCK_NOTARY_HOST'),
    'intercom_host' => env('A_BLOCK_INTERCOM_HOST'),
];
