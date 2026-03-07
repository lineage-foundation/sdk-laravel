<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use AWallet;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;
use Exception;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;

class RejectPendingTransaction extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:reject-pending-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a command rejects a pending trade request, from the receiver side';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->openWallet();
        $druid = $qtyResponse = $this->promptForNonEmptyString("What is the DRUID reference to the transaction?");

        $result = AWallet::rejectPendingTrasaction(
            druid: $druid,
        );

        dump($result);
    }
}
