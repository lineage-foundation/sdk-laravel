<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

class CreateTradeRequest extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:create-trade-request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a trade request between 2 addresses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $senderWallet = $this->openWallet(question: "Email address of initiator?");
        $selectedAssetsToSend = $this->assetsSelect();
        $myAddress = $this->keypairSelect(
            question: "Which address do you want to receive the assets in?",
            wallet: $senderWallet
        )->address;

        $otherPartyAddress = $this->promptForNonEmptyString("What is the address to send your assets to?");
        $receiveHash = $this->promptForNonEmptyString("What is the hash of the asset you wish to receive?", 'tokens');
        $receiveQty = $this->promptForNonEmptyString("How many $receiveHash you wish to receive?");

        $sendAsset = \Lineage::getPaymentAssetObject(
            amount: $selectedAssetsToSend['qty'],
            hash: $selectedAssetsToSend['name'],
            metaData: null
        );

        $receiveAsset = \Lineage::getPaymentAssetObject(
            amount: $receiveQty,
            hash: $receiveHash,
            metaData: null
        );

        $transaction = \Lineage::createTradeRequest(
            myAddress: $myAddress,
            myAsset: $sendAsset,
            otherPartyAddress: $otherPartyAddress,
            otherPartyAsset: $receiveAsset
        );

        dump($transaction);
    }
}
