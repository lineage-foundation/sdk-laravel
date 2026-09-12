<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;
use Lineage\Exceptions\NotImplemented;

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
     *
     * 2-way payments (trade requests) are deferred until the /v1 endpoints
     * for them land, so this surfaces the deferral rather than pretending
     * to open a wallet for an operation that cannot complete.
     */
    public function handle(): int
    {
        $druid = $this->promptForNonEmptyString("What is the DRUID reference to the transaction?");

        try {
            \Lineage::rejectPendingTransaction(druid: $druid);
        } catch (NotImplemented $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
