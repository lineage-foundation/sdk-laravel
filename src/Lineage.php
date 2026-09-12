<?php

namespace Lineage;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Lineage\Client;
use Lineage\DTO\EncryptedKeypairDTO;
use Lineage\DTO\EncryptedWalletDTO;
use Lineage\Exceptions\NameNotUniqueException;
use Lineage\Exceptions\PassPhraseNotSetException;
use Lineage\Models\LineageKeypair;
use Lineage\Models\LineageTransaction;
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
     * Offers a two-way (DRUID) trade to $otherPartyAddress via
     * Client::make2WayPayment, then persists the returned pending half
     * against the active wallet so a later getPendingTransactions() call
     * can settle it without the caller having to hand-manage the encrypted
     * half themselves — this is Laravel's value-add over the bare SDK.
     *
     * @param array $myAsset A Serialization asset array (Serialization::assetToken/assetItem).
     * @param array $otherPartyAsset A Serialization asset array (Serialization::assetToken/assetItem).
     */
    public function createTradeRequest(
        string $otherPartyAddress,
        mixed $myAsset,
        string $myAddress,
        mixed $otherPartyAsset,
    ): array {
        $receiveKeypair = $this->activeWallet->keypairs()->where('address', $myAddress)->first();

        if (!$receiveKeypair) {
            throw new Exception("No keypair found for address \"{$myAddress}\"");
        }

        $pendingHalf = $this->client->make2WayPayment(
            paymentAddress: $otherPartyAddress,
            sendingAsset: $myAsset,
            receivingAsset: $otherPartyAsset,
            allKeypairs: $this->getAllKeypairDTOs(),
            receiveKeypair: $this->toEncryptedKeypairDTO($receiveKeypair),
        );

        $this->activeWallet->transactions()->create([
            'druid' => $pendingHalf['druid'],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'encrypted_half' => $pendingHalf['encryptedHalf'],
            'sender_expectation' => $pendingHalf['senderExpectation'],
            'receiver_expectation' => $pendingHalf['receiverExpectation'],
        ]);

        return $pendingHalf;
    }

    /**
     * Polls for pending two-way (DRUID) trades via
     * Client::fetchPending2WayPayment, feeding it this wallet's own
     * previously-persisted pending halves so the SDK can settle any that
     * have since been accepted. Settled druids are marked accepted, and
     * newly discovered incoming offers are persisted (without an encrypted
     * half, since this wallet didn't initiate them) so a later
     * accept/rejectPendingTransaction($druid) call can look them up.
     *
     * @return array<string,array> The 'pending' offers discovered this poll, keyed by druid.
     */
    public function getPendingTransactions(): array
    {
        $storedRows = $this->activeWallet->transactions()
            ->where('status', Client::TRANSACTION_STATUS_PENDING)
            ->get();

        $storedPendingHalves = $storedRows
            ->filter(fn (LineageTransaction $row) => $row->encrypted_half !== null)
            ->map(fn (LineageTransaction $row) => [
                'druid' => $row->druid,
                'encryptedHalf' => $row->encrypted_half,
                'senderExpectation' => $row->sender_expectation,
                'receiverExpectation' => $row->receiver_expectation,
            ])
            ->values()
            ->all();

        $result = $this->client->fetchPending2WayPayment($storedPendingHalves, $this->getAllKeypairDTOs());

        if (!empty($result['settled'])) {
            $this->activeWallet->transactions()
                ->whereIn('druid', $result['settled'])
                ->update(['status' => Client::TRANSACTION_STATUS_ACCEPTED]);
        }

        $knownDruids = $storedRows->pluck('druid')->all();

        foreach ($result['pending'] as $druid => $details) {
            if (in_array($druid, $knownDruids, true)) {
                continue;
            }

            $this->activeWallet->transactions()->create([
                'druid' => $druid,
                'status' => $details['status'] ?? Client::TRANSACTION_STATUS_PENDING,
                'nonce' => '',
                'content' => '',
                'sender_expectation' => $details['senderExpectation'] ?? null,
                'receiver_expectation' => $details['receiverExpectation'] ?? null,
                'mempool_host' => $details['mempoolHost'] ?? null,
            ]);
        }

        return $result['pending'];
    }

    public function acceptPendingTransaction(string $druid): array
    {
        return $this->respondToPendingTransaction($druid, Client::TRANSACTION_STATUS_ACCEPTED);
    }

    public function rejectPendingTransaction(string $druid): array
    {
        return $this->respondToPendingTransaction($druid, Client::TRANSACTION_STATUS_REJECTED);
    }

    /**
     * Looks up the pending offer previously discovered by
     * getPendingTransactions() and delegates to Client::accept2WayPayment /
     * Client::reject2WayPayment, then reflects the resulting status back
     * onto the stored row.
     */
    private function respondToPendingTransaction(string $druid, string $status): array
    {
        $row = $this->activeWallet->transactions()->where('druid', $druid)->first();

        if (!$row) {
            throw new Exception("No pending trade request found for DRUID \"{$druid}\"");
        }

        $details = [
            'druid' => $row->druid,
            'senderExpectation' => $row->sender_expectation,
            'receiverExpectation' => $row->receiver_expectation,
            'status' => $row->status,
            'mempoolHost' => $row->mempool_host,
        ];

        $allKeypairs = $this->getAllKeypairDTOs();

        $result = $status === Client::TRANSACTION_STATUS_ACCEPTED
            ? $this->client->accept2WayPayment($details, $allKeypairs)
            : $this->client->reject2WayPayment($details, $allKeypairs);

        $row->update(['status' => $result['status'] ?? $status]);

        return $result;
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

    /**
     * @return array<EncryptedKeypairDTO>
     */
    private function getAllKeypairDTOs(): array
    {
        return $this->activeWallet->keypairs->map(
            fn (LineageKeypair $keypair) => $this->toEncryptedKeypairDTO($keypair)
        )->all();
    }
}
