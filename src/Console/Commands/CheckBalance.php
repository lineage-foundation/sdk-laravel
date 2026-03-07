<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use AWallet;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;
use Exception;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;

class CheckBalance extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:check-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a command checks the balance of a user wallet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wallet = $this->openWallet();
        $balance = AWallet::fetchBalance();

        dump($balance);
    }
}
