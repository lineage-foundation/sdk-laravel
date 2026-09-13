<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;
use Lineage\Serialization;

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
    public function handle(): int
    {
        try {
            $wallet = $this->openWallet();

            if (!$wallet) {
                return self::FAILURE;
            }

            $myKeypair = $this->keypairSelect($wallet, 'Which of your keypairs should receive the other party\'s asset?');
            $otherPartyAddress = $this->promptForNonEmptyString("What is the other party's address?");

            $myAmount = (int) $this->promptForNonEmptyString('How many tokens are you offering?');
            $otherPartyAmount = (int) $this->promptForNonEmptyString('How many tokens do you want in return?');

            $result = \Lineage::createTradeRequest(
                otherPartyAddress: $otherPartyAddress,
                myAsset: Serialization::assetToken($myAmount),
                myAddress: $myKeypair->address,
                otherPartyAsset: Serialization::assetToken($otherPartyAmount),
            );

            dump($result);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
