<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use AWallet;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;
use Exception;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;

class SendItemToAddress extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:send-item-to-address';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a command that creates a wallet for an existing user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->openWallet();
            $selectedAssets = $this->assetsSelect();
            $addressToSendTo = $this->promptForNonEmptyString("To which address do you want to send this?");

            $rs = AWallet::sendAssetToAddress(
                address: $addressToSendTo,
                asset: AWallet::getPaymentAssetObject(
                    amount: $selectedAssets['qty'],
                    hash: $selectedAssets['name'],
                    metaData: null
                ),
            );

            dump($rs);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
