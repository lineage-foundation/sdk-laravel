<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

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
     */
    public function handle(): int
    {
        try {
            $wallet = $this->openWallet();

            if (!$wallet) {
                return self::FAILURE;
            }

            $pending = \Lineage::getPendingTransactions();

            dump($pending);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
