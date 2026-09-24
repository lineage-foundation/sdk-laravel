<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Lineage /v1 endpoints
    |--------------------------------------------------------------------------
    |
    | mempool_host handles submissions and balance queries; storage_host serves
    | read APIs including item-metadata enrichment (GET /v1/items/{genesis_hash}),
    | which fetchBalance uses to attach each item's genesis metadata.
    |
    */

    'mempool_host' => env('LINEAGE_MEMPOOL_HOST'),
    'storage_host' => env('LINEAGE_STORAGE_HOST'),
    'api_key' => env('LINEAGE_API_KEY'),
    'valence_host' => env('LINEAGE_VALENCE_HOST'),
];
