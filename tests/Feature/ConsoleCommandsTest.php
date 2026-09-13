<?php

namespace Lineage\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lineage\Client;
use Lineage\Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_balance_command_outputs_balance_from_the_mocked_client(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())
            ->method('fetchBalance')
            ->willReturn(['total' => ['tokens' => 500, 'items' => []]]);

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
            'address' => 'address-1',
        ]);

        $this->artisan('lineage:check-balance')
            ->expectsQuestion("What is the user's email address?", 'ada@example.com')
            ->expectsChoice('Which wallet are we opening?', 'default', ['default'])
            ->expectsQuestion('Please enter the pass phrase for this wallet', 'secret')
            ->assertExitCode(0);
    }

    public function test_create_trade_request_command_delegates_to_the_client_and_persists_the_pending_half(): void
    {
        $pendingHalf = [
            'druid' => 'DRUID-cli',
            'encryptedHalf' => ['druid' => 'DRUID-cli', 'nonce' => 'tx-nonce', 'save' => 'tx-save'],
            'senderExpectation' => ['from' => '', 'to' => 'address-1', 'asset' => ['Token' => 20]],
            'receiverExpectation' => ['from' => '', 'to' => 'other-address', 'asset' => ['Token' => 10]],
        ];

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())
            ->method('make2WayPayment')
            ->willReturn($pendingHalf);

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
            'address' => 'address-1',
        ]);

        $this->artisan('lineage:create-trade-request')
            ->expectsQuestion("What is the user's email address?", 'ada@example.com')
            ->expectsChoice('Which wallet are we opening?', 'default', ['default'])
            ->expectsQuestion('Please enter the pass phrase for this wallet', 'secret')
            ->expectsChoice("Which of your keypairs should receive the other party's asset?", 'default', ['default'])
            ->expectsQuestion("What is the other party's address?", 'other-address')
            ->expectsQuestion('How many tokens are you offering?', '10')
            ->expectsQuestion('How many tokens do you want in return?', '20')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lineage_transactions', [
            'lineage_wallet_id' => $wallet->id,
            'druid' => 'DRUID-cli',
            'status' => Client::TRANSACTION_STATUS_PENDING,
        ]);
    }

    public function test_get_pending_transactions_command_calls_the_client_and_outputs_the_pending_offers(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())
            ->method('fetchPending2WayPayment')
            ->willReturn(['pending' => [], 'settled' => []]);

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
            'address' => 'address-1',
        ]);

        $this->artisan('lineage:get-pending-transactions')
            ->expectsQuestion("What is the user's email address?", 'ada@example.com')
            ->expectsChoice('Which wallet are we opening?', 'default', ['default'])
            ->expectsQuestion('Please enter the pass phrase for this wallet', 'secret')
            ->assertExitCode(0);
    }

    public function test_accept_pending_transaction_command_delegates_to_the_client(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())
            ->method('accept2WayPayment')
            ->willReturn(['druid' => 'druid-123', 'status' => Client::TRANSACTION_STATUS_ACCEPTED]);

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
            'address' => 'address-1',
        ]);
        $wallet->transactions()->create([
            'druid' => 'druid-123',
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'sender_expectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiver_expectation' => ['from' => '', 'to' => 'address-1', 'asset' => ['Token' => 1]],
            'mempool_host' => 'https://mempool.example',
        ]);

        $this->artisan('lineage:accept-pending-transaction')
            ->expectsQuestion("What is the user's email address?", 'ada@example.com')
            ->expectsChoice('Which wallet are we opening?', 'default', ['default'])
            ->expectsQuestion('Please enter the pass phrase for this wallet', 'secret')
            ->expectsQuestion('What is the DRUID reference to the transaction?', 'druid-123')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => 'druid-123',
            'status' => Client::TRANSACTION_STATUS_ACCEPTED,
        ]);
    }

    public function test_reject_pending_transaction_command_delegates_to_the_client(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())
            ->method('reject2WayPayment')
            ->willReturn(['druid' => 'druid-123', 'status' => Client::TRANSACTION_STATUS_REJECTED]);

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
            'address' => 'address-1',
        ]);
        $wallet->transactions()->create([
            'druid' => 'druid-123',
            'status' => Client::TRANSACTION_STATUS_PENDING,
            'nonce' => '',
            'content' => '',
            'sender_expectation' => ['from' => '', 'to' => 'someone-else', 'asset' => ['Token' => 1]],
            'receiver_expectation' => ['from' => '', 'to' => 'address-1', 'asset' => ['Token' => 1]],
            'mempool_host' => 'https://mempool.example',
        ]);

        $this->artisan('lineage:reject-pending-transaction')
            ->expectsQuestion("What is the user's email address?", 'ada@example.com')
            ->expectsChoice('Which wallet are we opening?', 'default', ['default'])
            ->expectsQuestion('Please enter the pass phrase for this wallet', 'secret')
            ->expectsQuestion('What is the DRUID reference to the transaction?', 'druid-123')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lineage_transactions', [
            'druid' => 'druid-123',
            'status' => Client::TRANSACTION_STATUS_REJECTED,
        ]);
    }
}
