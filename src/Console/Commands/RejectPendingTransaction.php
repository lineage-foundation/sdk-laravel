<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

class RejectPendingTransaction extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:reject-pending-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reject a pending trade request from the receiver side';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->openWallet();
        $druid = $this->promptForNonEmptyString("What is the DRUID reference to the transaction?");

        $result = \Lineage::rejectPendingTrasaction(
            druid: $druid,
        );

        dump($result);
    }
}
