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
    public function handle(): int
    {
        try {
            $wallet = $this->openWallet();

            if (!$wallet) {
                return self::FAILURE;
            }

            $druid = $this->promptForNonEmptyString('What is the DRUID reference to the transaction?');

            $result = \Lineage::rejectPendingTransaction(druid: $druid);

            dump($result);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
