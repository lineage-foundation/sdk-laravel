<?php

namespace Lineage\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lineage\Client;
use Lineage\DTO\EncryptedKeypairDTO;
use Lineage\DTO\EncryptedWalletDTO;
use Lineage\Facades\LineageFacade as Lineage;
use Lineage\Models\LineageTransaction;
use Lineage\Models\LineageWallet;

class TwoWayTest extends TestCase
{
    use RefreshDatabase;

    private function openWalletWithKeypair(Client $mockClient, string $address = 'my-address'): LineageWallet
    {
        $this->app->singleton(Client::class, fn () => $mockClient);

        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $wallet = $user->lineageWallets()->create([
            'name' => 'default',
            'master_key_encrypted_base64' => 'encrypted-master-key-base64',
            'nonce_hex' => 'wallet-nonce-hex',
        ]);

        $wallet->keypairs()->create([
            'name' => 'default',
            'save' => 'keypair-save-blob',
            'nonce' => 'keypair-nonce',
            'address' => $address,
        ]);

        Lineage::setActive($wallet, 'secret');

        return $wallet->fresh();
    }

    public function test_create_trade_request_delegates_and_persists_the_pending_half(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);

        $pendingHalf = [
            'druid' => 'DRUID0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'encryptedHalf' => ['druid' => 'DRUID0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'nonce' => 'tx-nonce', 'save' => 'tx-save'],
            'senderExpectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 50]],
            'receiverExpectation' => ['from' => '', 'to' => 'other-address', 'asset' => ['Token' => 100]],
        ];

        $mockClient->expects($this->once())
            ->method('make2WayPayment')
            ->with(
                'other-address',
                ['Token' => 100],
                ['Token' => 50],
                [new EncryptedKeypairDTO('my-address', 'keypair-nonce', 'keypair-save-blob')],
                new EncryptedKeypairDTO('my-address', 'keypair-nonce', 'keypair-save-blob'),
            )
            ->willReturn($pendingHalf);

        $wallet = $this->openWalletWithKeypair($mockClient);

        $result = Lineage::createTradeRequest(
            otherPartyAddress: 'other-address',
            myAsset: ['Token' => 100],
            myAddress: 'my-address',
            otherPartyAsset: ['Token' => 50],
        );

        $this->assertSame($pendingHalf, $result);

        $this->assertDatabaseHas('lineage_transactions', [
            'lineage_wallet_id' => $wallet->id,
            'druid' => $pendingHalf['druid'],
            'status' => Client::TRANSACTION_STATUS_PENDING,
        ]);

        $row = LineageTransaction::where('druid', $pendingHalf['druid'])->first();
        $this->assertSame($pendingHalf['encryptedHalf'], $row->encrypted_half);
        $this->assertSame($pendingHalf['senderExpectation'], $row->sender_expectation);
        $this->assertSame($pendingHalf['receiverExpectation'], $row->receiver_expectation);
    }

    public function test_get_pending_transactions_loads_stored_halves_and_reports_settled_and_pending(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);

        $storedHalf = [
            'druid' => 'DRUID-settled',
            'encryptedHalf' => ['druid' => 'DRUID-settled', 'nonce' => 'stored-nonce', 'save' => 'stored-save'],
            'senderExpectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 50]],
            'receiverExpectation' => ['from' => '', 'to' => 'other-address', 'asset' => ['Token' => 100]],
        ];

        $incomingOffer = [
            'druid' => 'DRUID-incoming',
            'senderExpectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiverExpectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 1]],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'mempoolHost' => 'https://mempool.example',
        ];

        $mockClient->expects($this->once())
            ->method('fetchPending2WayPayment')
            ->with(
                [$storedHalf],
                [new EncryptedKeypairDTO('my-address', 'keypair-nonce', 'keypair-save-blob')],
            )
            ->willReturn([
                'pending' => [$incomingOffer['druid'] => $incomingOffer],
                'settled' => [$storedHalf['druid']],
            ]);

        $wallet = $this->openWalletWithKeypair($mockClient);

        $wallet->transactions()->create([
            'druid' => $storedHalf['druid'],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'encrypted_half' => $storedHalf['encryptedHalf'],
            'sender_expectation' => $storedHalf['senderExpectation'],
            'receiver_expectation' => $storedHalf['receiverExpectation'],
        ]);

        $result = Lineage::getPendingTransactions();

        $this->assertSame([$incomingOffer['druid'] => $incomingOffer], $result);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => $storedHalf['druid'],
            'status' => Client::TRANSACTION_STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => $incomingOffer['druid'],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'mempool_host' => $incomingOffer['mempoolHost'],
        ]);
    }

    public function test_accept_pending_transaction_delegates_to_the_client(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);

        $wallet = $this->openWalletWithKeypair($mockClient);

        $wallet->transactions()->create([
            'druid' => 'DRUID-to-accept',
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'sender_expectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiver_expectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 1]],
            'mempool_host' => 'https://mempool.example',
        ]);

        $expectedDetails = [
            'druid' => 'DRUID-to-accept',
            'senderExpectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiverExpectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 1]],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'mempoolHost' => 'https://mempool.example',
        ];

        $accepted = $expectedDetails;
        $accepted['status'] = Client::TRANSACTION_STATUS_ACCEPTED;

        $mockClient->expects($this->once())
            ->method('accept2WayPayment')
            ->with(
                $expectedDetails,
                [new EncryptedKeypairDTO('my-address', 'keypair-nonce', 'keypair-save-blob')],
            )
            ->willReturn($accepted);

        $result = Lineage::acceptPendingTransaction('DRUID-to-accept');

        $this->assertSame($accepted, $result);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => 'DRUID-to-accept',
            'status' => Client::TRANSACTION_STATUS_ACCEPTED,
        ]);
    }

    public function test_reject_pending_transaction_delegates_to_the_client(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);

        $wallet = $this->openWalletWithKeypair($mockClient);

        $wallet->transactions()->create([
            'druid' => 'DRUID-to-reject',
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'sender_expectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiver_expectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 1]],
            'mempool_host' => 'https://mempool.example',
        ]);

        $expectedDetails = [
            'druid' => 'DRUID-to-reject',
            'senderExpectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiverExpectation' => ['from' => '', 'to' => 'my-address', 'asset' => ['Token' => 1]],
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'mempoolHost' => 'https://mempool.example',
        ];

        $rejected = $expectedDetails;
        $rejected['status'] = Client::TRANSACTION_STATUS_REJECTED;

        $mockClient->expects($this->once())
            ->method('reject2WayPayment')
            ->with(
                $expectedDetails,
                [new EncryptedKeypairDTO('my-address', 'keypair-nonce', 'keypair-save-blob')],
            )
            ->willReturn($rejected);

        $result = Lineage::rejectPendingTransaction('DRUID-to-reject');

        $this->assertSame($rejected, $result);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => 'DRUID-to-reject',
            'status' => Client::TRANSACTION_STATUS_REJECTED,
        ]);
    }
}
