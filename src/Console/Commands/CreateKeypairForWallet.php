<?php

namespace Lineage\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;
use Lineage\Exceptions\NameNotUniqueException;

class CreateKeypairForWallet extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:create-keypair-for-wallet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a keypair for an existing wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wallet = $this->openWallet();

        do {
            try {
                $name = $this->promptForNonEmptyString('Please enter a name for this keypair');
                $keyPair = \Lineage::createKeypair($name);
            } catch (NameNotUniqueException $e) {
                $this->error('There is already a keypair for this wallet with that name');
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        } while (!isset($keyPair) || !!$keyPair === false);

        $this->line("Keypair '$name' created for wallet '{$wallet->name}' with address: {$keyPair->address}");
    }
}
