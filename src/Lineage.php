<?php

namespace Lineage;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Lineage\Client;
use Lineage\DTO\EncryptedKeypairDTO;
use Lineage\DTO\EncryptedWalletDTO;
use Lineage\Exceptions\NameNotUniqueException;
use Lineage\Exceptions\NotImplemented;
use Lineage\Exceptions\PassPhraseNotSetException;
use Lineage\Models\LineageKeypair;
use Lineage\Models\LineageWallet;

class Lineage
{
    private ?LineageWallet $activeWallet = null;

    public function __construct(
        private Client $client
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
            if (!$name) {
                throw new Exception('Name cannot be empty');
            }

            if (!$passPhrase) {
                throw new Exception('Passphrase cannot be empty');
            }

            $this->setPassPhrase($passPhrase);
            $walletDTO = $this->client->createWallet();

            $wallet = $owner->lineageWallets()->create([
                'name' => $name,
                'master_key_encrypted_base64' => $walletDTO->getMasterKeyEncrypted(),
                'nonce_hex'                   => $walletDTO->getNonce(),
            ]);

            $this->setActive($wallet, $passPhrase);

            return [
                'wallet' => $wallet,
                'seedPhrase' => $walletDTO->getSeedPhrase(),
            ];
        } catch (PassPhraseNotSetException $e) {
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            throw new NameNotUniqueException();
        }
    }

    public function setActive(LineageWallet $wallet, string $passPhrase): bool
    {
        $this->setPassPhrase($passPhrase);

        $walletDTO = new EncryptedWalletDTO(
            masterKeyEncrypted: $wallet->master_key_encrypted_base64,
            nonce: $wallet->nonce_hex
        );

        $this->client->openWallet($walletDTO);
        $this->activeWallet = $wallet;

        return true;
    }

    public function createKeypair(string $name): LineageKeypair
    {
        if (!$name) {
            throw new Exception('Keypair name cannot be empty');
        }

        try {
            $encryptedKeypairDTO = $this->client->createKeypair($this->getAddressList());

            return $this->activeWallet->keypairs()->create([
                'name'    => $name,
                'nonce'   => $encryptedKeypairDTO->getNonce(),
                'save'    => $encryptedKeypairDTO->getContent(),
                'address' => $encryptedKeypairDTO->getAddress(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new NameNotUniqueException();
        }
    }

    public function getBlockchainEntry(string $hash): array
    {
        return $this->client->getBlockchainEntry($hash);
    }

    public function getSupply(): array
    {
        return $this->client->getSupply();
    }

    public function getBlock(int $number): array
    {
        return $this->client->getBlock($number);
    }

    public function getLatestBlock(): array
    {
        return $this->client->getLatestBlock();
    }

    public function getTransactionStatus(array $hashes): array
    {
        return $this->client->getTransactionStatus($hashes);
    }

    public function fetchBalance(?array $addresses = null): array
    {
        $addressList = $addresses ?? $this->getAddressList();

        return $this->client->fetchBalance($addressList);
    }

    public function createItems(
        LineageKeypair $keyPair,
        bool $defaultGenesisHash = true,
        int $amount = 1000,
        ?string $metadata = null,
    ): array {
        return $this->client->createItems(
            keypair: $this->toEncryptedKeypairDTO($keyPair),
            defaultGenesisHash: $defaultGenesisHash,
            amount: $amount,
            metadata: $metadata,
        );
    }

    public function makeTokenPayment(
        string $address,
        int $amount,
        ?string $excessAddress = null,
        int $locktime = 0,
    ): array {
        [$allKeypairs, $excessKeypair] = $this->resolvePaymentKeypairs($excessAddress);

        return $this->client->makeTokenPayment(
            paymentAddress: $address,
            amount: $amount,
            allKeypairs: $allKeypairs,
            excessKeypair: $excessKeypair,
            locktime: $locktime,
        );
    }

    public function makeItemPayment(
        string $address,
        int $amount,
        string $genesisHash,
        ?string $metadata = null,
        ?string $excessAddress = null,
        int $locktime = 0,
    ): array {
        [$allKeypairs, $excessKeypair] = $this->resolvePaymentKeypairs($excessAddress);

        return $this->client->makeItemPayment(
            paymentAddress: $address,
            amount: $amount,
            genesisHash: $genesisHash,
            allKeypairs: $allKeypairs,
            excessKeypair: $excessKeypair,
            metadata: $metadata,
            locktime: $locktime,
        );
    }

    /**
     * 2-way payments (trade requests) are deferred until the /v1 endpoints
     * for them land. sdk-php's Client throws NotImplemented for this and the
     * rest of the DRUID trade surface below — we rethrow rather than build
     * against removed legacy endpoints.
     *
     * @throws NotImplemented
     */
    public function createTradeRequest(
        string $otherPartyAddress,
        mixed $myAsset,
        string $myAddress,
        mixed $otherPartyAsset,
    ): never {
        throw new NotImplemented();
    }

    /** @throws NotImplemented */
    public function getPendingTransactions(): never
    {
        throw new NotImplemented();
    }

    /** @throws NotImplemented */
    public function acceptPendingTransaction(string $druid): never
    {
        throw new NotImplemented();
    }

    /** @throws NotImplemented */
    public function rejectPendingTransaction(string $druid): never
    {
        throw new NotImplemented();
    }

    public function getActiveWallet(): ?LineageWallet
    {
        return $this->activeWallet;
    }

    private function getAddressList(): array
    {
        return $this->activeWallet->keypairs()->get()
            ->map(fn ($item) => $item->address)
            ->unique()
            ->toArray();
    }

    private function toEncryptedKeypairDTO(LineageKeypair $keyPair): EncryptedKeypairDTO
    {
        return new EncryptedKeypairDTO(
            address: $keyPair->address,
            nonce: $keyPair->nonce,
            content: $keyPair->save,
        );
    }

    /**
     * @return array{0: array<EncryptedKeypairDTO>, 1: EncryptedKeypairDTO}
     */
    private function resolvePaymentKeypairs(?string $excessAddress): array
    {
        $keypairs = $this->activeWallet->keypairs->map(
            fn (LineageKeypair $keypair) => $this->toEncryptedKeypairDTO($keypair)
        );

        $excessKeypair = $excessAddress
            ? $keypairs->first(fn (EncryptedKeypairDTO $dto) => $dto->getAddress() === $excessAddress)
            : $keypairs->first();

        if (!$excessKeypair) {
            throw new Exception('No keypair available to receive payment excess');
        }

        return [$keypairs->all(), $excessKeypair];
    }
}
