<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use AWallet;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;
use Exception;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;

class GetPendingTransactions extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:get-pending-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a command that fetches all pending trade requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->openWallet();
        $transactions = AWallet::getPendingTransactions();
        dump($transactions);
    }
}
