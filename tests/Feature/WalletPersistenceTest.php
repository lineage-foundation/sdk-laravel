<?php

namespace Lineage\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lineage\Client;
use Lineage\DTO\EncryptedKeypairDTO;
use Lineage\DTO\EncryptedWalletDTO;
use Lineage\Facades\LineageFacade as Lineage;
use Lineage\Models\LineageKeypair;
use Lineage\Models\LineageWallet;
use Lineage\Tests\TestCase;

class WalletPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_wallet_and_keypair_persists_the_new_keystore_fields(): void
    {
        $walletDTO = new EncryptedWalletDTO(
            masterKeyEncrypted: 'encrypted-master-key-base64',
            nonce: 'wallet-nonce-hex',
            seedPhrase: 'seed phrase words here',
        );

        $keypairDTO = new EncryptedKeypairDTO(
            address: 'address-1',
            nonce: 'keypair-nonce',
            content: 'keypair-save-blob',
        );

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())->method('createWallet')->willReturn($walletDTO);
        $mockClient->expects($this->once())->method('openWallet')->willReturn(true);
        $mockClient->expects($this->once())->method('createKeypair')->willReturn($keypairDTO);

        $this->app->singleton(Client::class, fn () => $mockClient);

        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $result = Lineage::create(
            owner: $user,
            name: 'default',
            passPhrase: 'secret',
        );

        $keypair = Lineage::createKeypair('default');

        $this->assertDatabaseHas('lineage_wallets', [
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'name' => 'default',
            'master_key_encrypted_base64' => 'encrypted-master-key-base64',
            'nonce_hex' => 'wallet-nonce-hex',
        ]);

        $this->assertDatabaseHas('lineage_keypairs', [
            'lineage_wallet_id' => $result['wallet']->id,
            'name' => 'default',
            'save' => 'keypair-save-blob',
            'nonce' => 'keypair-nonce',
            'address' => 'address-1',
        ]);

        $this->assertInstanceOf(LineageWallet::class, $result['wallet']);
        $this->assertSame('seed phrase words here', $result['seedPhrase']);
        $this->assertInstanceOf(LineageKeypair::class, $keypair);
    }
}
