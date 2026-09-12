<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Lineage endpoints
    |--------------------------------------------------------------------------
    |
    */

    'proxy_url' => env('LINEAGE_PROXY_URL'),
    'compute_host' => env('LINEAGE_COMPUTE_HOST'),
    'storage_host' => env('LINEAGE_STORAGE_HOST'),
    'notary_host' => env('LINEAGE_NOTARY_HOST'),
    'intercom_host' => env('LINEAGE_INTERCOM_HOST'),
];
