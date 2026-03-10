<?php

namespace IODigital\ABlockLaravel;

use IODigital\ABlockPHP\ABlockClient;
use Illuminate\Database\Eloquent\Model;
use IODigital\ABlockLaravel\Models\ABlockWallet;
use IODigital\ABlockLaravel\Models\ABlockKeypair;
use IODigital\ABlockLaravel\Models\ABlockTransaction;
use IODigital\ABlockPHP\DTO\EncryptedWalletDTO;
use IODigital\ABlockPHP\DTO\PaymentAssetDTO;
use Illuminate\Database\UniqueConstraintViolationException;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockLaravel\Exceptions\NameNotUniqueException;
use Exception;

class AWallet
{
    private ?ABlockWallet $activeWallet;

    public function __construct(
        private ABlockClient $client
    ) {}

    public function setPassPhrase(string $passPhrase): void
    {
        $this->client->setPassPhrase($passPhrase);
    }

    public function create(
        Model $owner,
        string $name,
        string $passPhrase,
    ): array {
        try {
            if(!$name) {
                throw new Exception('Name cannot be empty');
            }

            if(!$passPhrase) {
                throw new Exception('Passphrase cannot be empty');
            }

            $this->setPassPhrase($passPhrase);
            $walletDTO = $this->client->createWallet();

            $wallet = $owner->aBlockWallets()->create([
                'name' => $name,
                'master_key_encrypted_base64' => $walletDTO->getMasterKeyEncrypted(),
                'nonce_hex'                   => $walletDTO->getNonce(),
            ]);

            $this->setActive($wallet, $passPhrase);

            return [
                'wallet' => $wallet,
                'seedPhrase' => $walletDTO->getSeedPhrase()
            ];

        } catch (PassPhraseNotSetException $e) {
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            throw new NameNotUniqueException();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function setActive(ABlockWallet $wallet, string $passPhrase): bool
    {
        try {
            $this->setPassPhrase($passPhrase);

            $walletDTO = new EncryptedWalletDTO(
                masterKeyEncrypted: $wallet->master_key_encrypted_base64,
                nonce: $wallet->nonce_hex
            );

            $this->client->openWallet($walletDTO);
            $this->activeWallet = $wallet;

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createKeypair(string $name): ABlockKeypair
    {
        try {
            if(!$name) {
                throw new Exception('Keypair name cannot be empty');
            }
            $encryptedKeypairDTO = $this->client->createKeypair($this->getAddressList());

            return $this->activeWallet->keypairs()->create([
                'name'    => $name,
                'nonce'   => $encryptedKeypairDTO->getNonce(),
                'save'    => $encryptedKeypairDTO->getContent(),
                'address' => $encryptedKeypairDTO->getAddress(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new NameNotUniqueException();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getBlockchainEntry(string $hash): array
    {
        try {
            return $this->client->getBlockchainEntry($hash);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function fetchBalance(?array $addresses = null): array
    {
        try {
            $addressList = $addresses ?? $this->getAddressList();
            return $this->client->fetchBalance($addressList);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createAsset(
        ABlockKeypair $keyPair,
        string $name,
        int $amount = 1,
        bool $defaultHash = false,
        ?array $metaData = [],
    ): PaymentAssetDTO {
        try {
            return $this->client->createAsset(
                name: $name,
                encryptedKey: $keyPair->save,
                nonce: $keyPair->nonce,
                amount: $amount,
                defaultHash: $defaultHash,
                metaData: $metaData
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function sendAssetToAddress(
        string $address,
        PaymentAssetDTO $asset,
        string $excessAddress = null
    ): array {
        try {
            $senderKeyPairs = $this->getActiveWalletKeypairs();

            return $this->client->sendAssetToAddress(
                senderKeypairs: $senderKeyPairs,
                address: $address,
                asset: $asset,
                excessAddress: $excessAddress
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createTradeRequest(
        string $otherPartyAddress,
        string $myAddress,
        PaymentAssetDTO $myAsset,
        PaymentAssetDTO $otherPartyAsset
    ): ABlockTransaction {
        $keypairs = $this->getActiveWalletKeypairs();

        $encryptedTransaction = $this->client->createTradeRequest(
            myKeypairs: $keypairs,
            myAddress: $myAddress,
            otherPartyAsset: $otherPartyAsset,
            otherPartyAddress: $otherPartyAddress,
            myAsset: $myAsset
        );

        return $this->activeWallet->transactions()->create([
            'druid' => $encryptedTransaction['druid'],
            'nonce' => $encryptedTransaction['nonce'],
            'content' => $encryptedTransaction['save']
        ]);
    }

    public function getPendingTransactions(): array
    {
        try {
            $keypairs = $this->getActiveWalletKeypairs();
            $pendingTransactions = $this->activeWallet->transactions()->get();

            $encryptedTransactionMap = $pendingTransactions->mapWithKeys(fn($item) => [
                $item->druid => [
                    'save' => $item->content,
                    'nonce' => $item->nonce,
                    'druid' => $item->druid
                ]
            ])->toArray();

            return $this->client->getPendingTransactions(
                keypairs: $keypairs,
                encryptedTransactionMap: $encryptedTransactionMap
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function acceptPendingTransaction(
        string $druid,
    ): array {
        try {
            return $this->client->acceptPendingTransaction(
                druid: $druid,
                keypairs: $this->getActiveWalletKeypairs(),
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function rejectPendingTrasaction(
        string $druid
    ): array {
        try {
            return $this->client->rejectPendingTransaction(
                druid: $druid,
                keypairs: $this->getActiveWalletKeypairs(),
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getPaymentAssetObject(int $amount, ?string $hash = null, ?array $metaData = null): PaymentAssetDTO
    {
        return $this->client->getPaymentAssetObject(
            amount: $amount,
            hash: !$hash || $hash === 'tokens' ? null : $hash,
            metaData: $metaData
        );
    }

    public function getActiveWallet(): ABlockWallet|null
    {
        return $this->activeWallet ?? null;
    }

    private function getAddressList(): array
    {
        return $this->activeWallet->keypairs()->get()
        ->map(fn($item) => $item->address)
        ->unique()
        ->toArray();
    }

    private function getActiveWalletKeypairs(): array
    {
        return $this->activeWallet->keypairs->mapWithKeys(fn($item) => [$item->address => [
            'encryptedKey' => $item->save,
            'nonce' => $item->nonce
        ]])->toArray();
    }
}
