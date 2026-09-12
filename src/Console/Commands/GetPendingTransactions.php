<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;
use Lineage\Exceptions\NotImplemented;

class GetPendingTransactions extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:get-pending-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all pending trade requests';

    /**
     * Execute the console command.
     *
     * 2-way payments (trade requests) are deferred until the /v1 endpoints
     * for them land, so this command surfaces the deferral immediately
     * rather than walking the wallet-opening flow first.
     */
    public function handle(): int
    {
        try {
            \Lineage::getPendingTransactions();
        } catch (NotImplemented $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
