<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use AWallet;
use Exception;
use Illuminate\Console\Command;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;

class CreateKeypairForWallet extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:create-keypair-for-wallet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a command that creates a keypair for an existing wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wallet = $this->openWallet();

        do {
            try {
                $name = $this->promptForNonEmptyString('Please enter a name for this keypair');
                $keyPair = AWallet::createKeypair($name);
            } catch (NameNotUniqueException $e) {
                $this->error('There is already a keypair for this wallet with that name');
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        } while (!isset($keyPair) || !!$keyPair === false);

        $this->line("Keypair '$name' created for wallet '{$wallet->name}' with address: {$keyPair->address}");
    }
}
