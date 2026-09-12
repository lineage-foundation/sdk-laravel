<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

class CreateItem extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:create-item';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an item in a user wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wallet = $this->openWallet();
        $keyPair = $this->keypairSelect(wallet: $wallet);

        do {
            $qtyResponse = $this->promptForNonEmptyString("How many items?");

            if(is_numeric($qtyResponse) && is_integer((int) $qtyResponse) && $qtyResponse > 0) {
                $qty = (int) $qtyResponse;
            } else {
                $this->error("Please enter an integer number larger than 0");
            }
        } while (!isset($qty));

        $metadata = $this->ask("Any metadata for this item? (optional)");
        $useDefaultGenesisHash = $this->confirm("Use the default genesis hash?", true);

        $item = \Lineage::createItems(
            keyPair: $keyPair,
            defaultGenesisHash: $useDefaultGenesisHash,
            amount: $qty,
            metadata: $metadata ?: null,
        );

        $this->line("$qty item(s) created for keypair '{$keyPair->name}'");
        dump($item);
    }
}
