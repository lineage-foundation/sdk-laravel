<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

class SendItemToAddress extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:send-item-to-address';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an item to an address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->openWallet();
            $selectedAssets = $this->assetsSelect();
            $addressToSendTo = $this->promptForNonEmptyString("To which address do you want to send this?");

            if ($selectedAssets['name'] === 'tokens') {
                $rs = \Lineage::makeTokenPayment(
                    address: $addressToSendTo,
                    amount: $selectedAssets['qty'],
                );
            } else {
                $rs = \Lineage::makeItemPayment(
                    address: $addressToSendTo,
                    amount: $selectedAssets['qty'],
                    genesisHash: $selectedAssets['name'],
                );
            }

            dump($rs);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
