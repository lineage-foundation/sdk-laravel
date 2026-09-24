# lineage/laravel

Laravel wrapper for the Lineage blockchain `/v1` API: wallets and keypairs backed by
Eloquent models, plus Artisan commands for creating wallets, deriving keypairs,
minting items, and making token/item payments.

Under the hood this package wraps [`lineage/php`](https://github.com/lineage-foundation/sdk-php),
which talks directly to the `/v1` mempool/storage API.

## Installation

Published on [Packagist](https://packagist.org/packages/lineage/laravel) as `lineage/laravel`.

```bash
composer require lineage/laravel
```

## Configuration

`lineage/laravel` talks to three hosts:

- **`LINEAGE_MEMPOOL_HOST`** – the node that accepts writes (wallets, keypairs,
  items, payments) and answers live queries (balances, supply, transaction status).
- **`LINEAGE_STORAGE_HOST`** – the node that serves stored chain history (blocks,
  blockchain entries). This can be the same host as the mempool, or a dedicated
  read/storage node.
- **`LINEAGE_VALENCE_HOST`** – required for 2-way payments only: the mailbox
  service `createTradeRequest`/`getPendingTransactions`/`acceptPendingTransaction`/
  `rejectPendingTransaction` use to exchange DRUID trade offers between parties.
- **`LINEAGE_API_KEY`** – optional, sent as the `x-api-key` header on every request.

Add to your `.env`:

```
LINEAGE_MEMPOOL_HOST=https://mempool.lineage.to
LINEAGE_STORAGE_HOST=https://storage.lineage.to
LINEAGE_VALENCE_HOST=https://valence.lineage.to
LINEAGE_API_KEY=
```

These map straight onto `config/lineage.php` (`mempool_host`, `storage_host`,
`valence_host`, `api_key`). Publish it if you want to customize further:

```bash
php artisan vendor:publish --tag=lineage-config
```

Run the package's migrations to create the `lineage_wallets`, `lineage_keypairs` and
`lineage_transactions` tables:

```bash
php artisan migrate
```

## Usage

Add the `HasLineageWallets` trait to any model that owns wallets (e.g. your `User`
model):

```php
use Lineage\Traits\HasLineageWallets;

class User extends Authenticatable
{
    use HasLineageWallets;
}
```

This provides:
- `lineageWallets()` – `MorphMany` relationship to the owner's wallets
- `openDefaultLineageWallet(string $passPhrase)` – open the owner's default wallet

### Quickstart

Everything below goes through the `Lineage` facade (`\Lineage::...`), which resolves
to a `Lineage\Lineage` instance wired up with the `lineage/php` `Client` from your
config.

```php
use Lineage;

// Create a wallet for a model that uses HasLineageWallets. The pass phrase
// encrypts the wallet's master key at rest; the returned seed phrase is the
// only way to recover funds if it's lost — show it to the user once and
// don't store it yourself.
$result = Lineage::create($user, 'primary', 'my very intricate passphrase');
$wallet = $result['wallet'];         // Lineage\Models\LineageWallet
$seedPhrase = $result['seedPhrase']; // 12-word BIP39 seed phrase

// Open it as the active wallet for subsequent calls (create() already does
// this for the wallet it just made — call setActive() when opening an
// existing wallet in a later request).
Lineage::setActive($wallet, 'my very intricate passphrase');

// Derive a keypair/address from the active wallet.
$keypair = Lineage::createKeypair('primary-address'); // Lineage\Models\LineageKeypair
echo $keypair->address;

// Check balances across every keypair on the active wallet.
$balance = Lineage::fetchBalance();

// Mint 10 item assets ("Items", the Lineage equivalent of NFTs) at $keypair's
// address, using the well-known default genesis hash.
Lineage::createItems($keypair, defaultGenesisHash: true, amount: 10);

// Send 1000 Token assets from the active wallet to another address, with
// change (excess) returned to the wallet itself.
Lineage::makeTokenPayment(address: $recipientAddress, amount: 1000);
```

Note that a newly created/transferred asset only shows up in a subsequent
`fetchBalance()` call once it has been confirmed by the mempool.

### Item metadata enrichment

`fetchBalance()` attaches each item's genesis `metadata` by default, resolved from
the storage node (`config('lineage.storage_host')` / `LINEAGE_STORAGE_HOST`). Pass
`enrich: false` to skip it (zero resolver calls, items returned unmodified):

```php
$balance = Lineage::fetchBalance($addresses, enrich: false);
```

Use `getItemInfo()` to resolve an item's full genesis facts by its `genesis_hash`
— its `metadata`, `total_amount`, `created.block_num` / `created.tx_hash`, and
`creator_address`:

```php
$info = Lineage::getItemInfo($genesisHash);
```

This requires `LINEAGE_STORAGE_HOST` to be configured. Enrichment is best-effort:
if the resolver is unavailable, `fetchBalance()` still returns — items keep
whatever `metadata` the balance already carried, or `null` for items that have
since been transferred.

## Two-way (DRUID) payments

`createTradeRequest`, `getPendingTransactions`, `acceptPendingTransaction` and
`rejectPendingTransaction` (DRUID-based dual double-entry trades) delegate to
`lineage/php`'s `Client::make2WayPayment` / `fetchPending2WayPayment` /
`accept2WayPayment` / `reject2WayPayment`. On top of that, `createTradeRequest`
persists the initiator's pending half (druid, encrypted half, and both
expectations) to a `lineage_transactions` row, and `getPendingTransactions`
uses those stored rows to settle accepted offers and to remember incoming
offers so a later `acceptPendingTransaction($druid)` /
`rejectPendingTransaction($druid)` call can look them up — callers never have
to hand-manage the encrypted half themselves.

```php
// Offer to trade 100 tokens for the other party's 50 tokens.
$pending = Lineage::createTradeRequest(
    otherPartyAddress: $otherPartyAddress,
    myAsset: Serialization::assetToken(100),
    myAddress: $keypair->address,
    otherPartyAsset: Serialization::assetToken(50),
);

// Poll for settlement/incoming offers.
$incoming = Lineage::getPendingTransactions();

// Accept or reject an incoming offer by its DRUID.
Lineage::acceptPendingTransaction($druid);
Lineage::rejectPendingTransaction($druid);
```

Two-way trades interoperate across all the SDKs and settle atomically through the
mempool's DRUID pool, so either party can be on any SDK.

### Artisan commands

Interactive CLI:

```bash
php artisan lineage:command-app
```

Individual commands:
- `lineage:create-wallet-for-user` – create a wallet for an existing user
- `lineage:check-balance` – check wallet balance
- `lineage:create-keypair-for-wallet` – create a keypair
- `lineage:create-item` – mint item assets in a wallet
- `lineage:send-item-to-address` – send tokens or an item to an address
- `lineage:create-trade-request` – offer a 2-way (DRUID) token trade
- `lineage:get-pending-transactions` – poll for settled/incoming 2-way trades
- `lineage:accept-pending-transaction` – accept an incoming 2-way trade by DRUID
- `lineage:reject-pending-transaction` – reject an incoming 2-way trade by DRUID

## Wire compatibility

Keys and signatures are byte-for-byte compatible across every Lineage SDK — a wallet
(mnemonic) created in one derives the same addresses and produces the same signatures
in all of them. sdk-js is the reference implementation; BIP39/BIP32 derivation,
SHA3-256 addresses, ed25519 signing, and the `/v1` transaction serialization (field
order is load-bearing — you sign exactly what you submit) all match it exactly.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Lineage SDKs

- [JavaScript / TypeScript](https://github.com/lineage-foundation/sdk-js)
- [Python](https://github.com/lineage-foundation/sdk-python)
- [Go](https://github.com/lineage-foundation/sdk-go)
- [Rust](https://github.com/lineage-foundation/sdk-rust)
- [PHP](https://github.com/lineage-foundation/sdk-php)
- [Laravel](https://github.com/lineage-foundation/sdk-laravel)

## License

MIT — see [LICENSE](LICENSE).
