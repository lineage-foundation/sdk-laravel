<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;

class CheckBalance extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:check-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the balance of a user wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->openWallet();
        $balance = \Lineage::fetchBalance();

        dump($balance);
    }
}
