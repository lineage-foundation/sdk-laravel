# lineage/laravel

Laravel wrapper for the Lineage blockchain SDK.

## Installation

```bash
composer require lineage/laravel
```

## Configuration

Add to your `.env`:

```
LINEAGE_COMPUTE_HOST=https://compute.aiblock.dev
LINEAGE_STORAGE_HOST=https://storage.aiblock.dev
LINEAGE_INTERCOM_HOST=https://intercom.aiblock.dev
```

Publish the config file:

```bash
php artisan vendor:publish --tag=lineage-config
```

## Usage

Add the `HasLineageWallets` trait to any model that has wallets (e.g. your User model):

```php
use Lineage\Traits\HasLineageWallets;

class User extends Authenticatable
{
    use HasLineageWallets;
}
```

This provides:
- `lineageWallets()` – relationship to wallets
- `openDefaultLineageWallet(string $passPhrase)` – open the user’s default wallet

### Artisan commands

Interactive CLI:

```bash
php artisan lineage:command-app
```

Other commands:
- `lineage:create-wallet-for-user` – create a wallet for an existing user
- `lineage:check-balance` – check wallet balance
- `lineage:create-keypair-for-wallet` – create a keypair
- `lineage:create-item` – create an item in a wallet
- `lineage:send-item-to-address` – send an item to an address
- `lineage:get-pending-transactions` – list pending transactions
- `lineage:create-trade-request` – create a trade request
- `lineage:accept-pending-transaction` – accept a pending transaction
- `lineage:reject-pending-transaction` – reject a pending transaction

## Links

- [Lineage Foundation](https://lineage.foundation)
- [Other SDKs](https://github.com/lineage-foundation) – sdk-python, sdk-php, sdk-js, sdk-laravel

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## LicenseMIT – see [LICENSE](LICENSE).
