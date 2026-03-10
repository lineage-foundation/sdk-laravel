<?php

namespace IODigital\ABlockLaravel\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use AWallet;
use IODigital\ABlockPHP\Exceptions\PassPhraseNotSetException;
use IODigital\ABlockPHP\Exceptions\NameNotUniqueException;
use Exception;
use IODigital\ABlockLaravel\Console\Traits\UserWallets;

class CommandApp extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ablock:command-app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This runs the commands in a flow';

    public const COMMAND_OPEN_WALLET = 'Open Wallet';
    public const COMMAND_CREATE_WALLET = 'Create New Wallet';
    public const COMMAND_BALANCE_ENQUIRY = 'Balance Enquiry';
    public const COMMAND_CREATE_KEYPAIR = 'Create Keypair';
    public const COMMAND_SHOW_KEYPAIRS = 'Show All Keypairs';
    public const COMMAND_CREATE_ITEM = 'Create Item';
    public const COMMAND_SEND_ITEM = 'Send Item';
    public const COMMAND_GET_PENDING_TRANSACTIONS = 'Get Pending Transactions';
    public const COMMAND_MAKE_TRADE_REQUEST = 'Make Trade Request';
    public const COMMAND_ACCEPT_PENDING_TRANSACTION = 'Accept Pending Transaction';
    public const COMMAND_REJECT_PENDING_TRANSACTION = 'Reject Pending Transaction';
    public const COMMAND_GET_BLOCKCHAIN_ENTRY = 'Get Blockchain Entry';
    public const COMMAND_GO_BACK = '<<< Go Back';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line("Welcome to the ABlock command line app.");
        while (true) {
            $action = $this->choice(
                "Let's get started",
                [
                    self::COMMAND_GO_BACK,
                    self::COMMAND_OPEN_WALLET,
                    self::COMMAND_CREATE_WALLET,
                    self::COMMAND_GET_BLOCKCHAIN_ENTRY
                ],
                self::COMMAND_OPEN_WALLET,
            );

            switch($action) {
                case self::COMMAND_OPEN_WALLET:
                    $this->openWallet(closeExisting: true);
                    break;
                case self::COMMAND_CREATE_WALLET:
                    $this->call('ablock:create-wallet-for-user');
                    break;
                case self::COMMAND_GO_BACK:
                    $this->line("Bye!");
                    break(2);
                case self::COMMAND_GET_BLOCKCHAIN_ENTRY:
                    $hash = $this->ask("Enter the hash");
                    $this->getBlockchainEntry($hash);
                    break;
                default:
            }

            $wallet = AWallet::getActiveWallet();
            if(!$wallet) {
                continue;
            }

            while (true) {
                $this->newLine();
                $this->line("The open wallet is '{$wallet->name}' for user '{$wallet->owner->email}'");
                $action = $this->choice(
                    'What would you like to do?',
                    [
                        self::COMMAND_GO_BACK,
                        self::COMMAND_BALANCE_ENQUIRY,
                        self::COMMAND_SHOW_KEYPAIRS,
                        self::COMMAND_CREATE_KEYPAIR,
                        self::COMMAND_CREATE_ITEM,
                        self::COMMAND_SEND_ITEM,
                        self::COMMAND_GET_PENDING_TRANSACTIONS,
                        self::COMMAND_MAKE_TRADE_REQUEST,
                        self::COMMAND_ACCEPT_PENDING_TRANSACTION,
                        self::COMMAND_REJECT_PENDING_TRANSACTION,
                        self::COMMAND_GET_BLOCKCHAIN_ENTRY
                    ],
                    null,
                    $maxAttempts = null,
                    $allowMultipleSelections = false
                );

                switch($action) {
                    case self::COMMAND_GO_BACK:
                        break(2);
                    case self::COMMAND_BALANCE_ENQUIRY:
                        $this->call('ablock:check-balance');
                        break;
                    case self::COMMAND_CREATE_KEYPAIR:
                        $this->call('ablock:create-keypair-for-wallet');
                        break;
                    case self::COMMAND_SHOW_KEYPAIRS:
                        $this->table(
                            ['Name', 'Address'],
                            $wallet->keypairs()->get()->map(function ($item) {
                                return [
                                    'name' => $item->name,
                                    'address' => $item->address
                                ];
                            })->toArray()
                        );
                        break;
                    case self::COMMAND_CREATE_ITEM:
                        $this->call('ablock:create-item');
                        break;
                    case self::COMMAND_SEND_ITEM:
                        $this->call('ablock:send-item-to-address');
                        break;
                    case self::COMMAND_GET_PENDING_TRANSACTIONS:
                        $this->call('ablock:get-pending-transactions');
                        break;
                    case self::COMMAND_MAKE_TRADE_REQUEST:
                        $this->call('ablock:create-trade-request');
                        break;
                    case self::COMMAND_ACCEPT_PENDING_TRANSACTION:
                        $this->call('ablock:accept-pending-transaction');
                        break;
                    case self::COMMAND_REJECT_PENDING_TRANSACTION:
                        $this->call('ablock:reject-pending-transaction');
                        break;
                    case self::COMMAND_GET_BLOCKCHAIN_ENTRY:
                        $hash = $this->ask("Enter the hash");
                        $this->getBlockchainEntry($hash);
                        break;
                    default:
                }

                $this->line("---------------------------------------------------------------------------------------------------------");
            }
        }
    }

    private function getBlockchainEntry(string $hash)
    {
        try {
            $result = AWallet::getBlockchainEntry($hash);

            foreach($result['content']['Transaction']['outputs'] as $output) {
                dump($output['value']);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

    }
}
