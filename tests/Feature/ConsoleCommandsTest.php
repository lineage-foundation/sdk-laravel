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

    public function test_create_trade_request_command_surfaces_the_not_implemented_deferral(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->never())->method('createTradeRequest');

        $this->app->singleton(Client::class, fn () => $mockClient);

        $this->artisan('lineage:create-trade-request')
            ->assertExitCode(1)
            ->expectsOutputToContain('2-way payments deferred');
    }

    public function test_get_pending_transactions_command_surfaces_the_not_implemented_deferral(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->never())->method('getPendingTransactions');

        $this->app->singleton(Client::class, fn () => $mockClient);

        $this->artisan('lineage:get-pending-transactions')
            ->assertExitCode(1)
            ->expectsOutputToContain('2-way payments deferred');
    }

    public function test_accept_pending_transaction_command_surfaces_the_not_implemented_deferral(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->never())->method('acceptPendingTransaction');

        $this->app->singleton(Client::class, fn () => $mockClient);

        $this->artisan('lineage:accept-pending-transaction')
            ->expectsQuestion('What is the DRUID reference to the transaction?', 'druid-123')
            ->assertExitCode(1)
            ->expectsOutputToContain('2-way payments deferred');
    }

    public function test_reject_pending_transaction_command_surfaces_the_not_implemented_deferral(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->never())->method('rejectPendingTransaction');

        $this->app->singleton(Client::class, fn () => $mockClient);

        $this->artisan('lineage:reject-pending-transaction')
            ->expectsQuestion('What is the DRUID reference to the transaction?', 'druid-123')
            ->assertExitCode(1)
            ->expectsOutputToContain('2-way payments deferred');
    }
}
