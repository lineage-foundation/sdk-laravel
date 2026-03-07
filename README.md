If you can decipher this, use it.

`composer require io-digital/a-block-laravel`

Some env settings:

A_BLOCK_COMPUTE_HOST=https://compute.a-block.net
A_BLOCK_STORAGE_HOST=https://storage.a-block.net
A_BLOCK_INTERCOM_HOST=https://intercom.a-block.net

`php artisan vendor:publish --tag=a-block-config`

Add this trait to any model that has wallets
`use IODigital\ABlockLaravel\Traits\HasABlockWallets;`

The step into DOS world and run 
`php artisan ablock:command-app`

## Links

- [Lineage Foundation](https://lineage.foundation)
- [Other SDKs](https://github.com/lineage-foundation) – sdk-python, sdk-php, sdk-js, sdk-laravel

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT – see [LICENSE](LICENSE).
