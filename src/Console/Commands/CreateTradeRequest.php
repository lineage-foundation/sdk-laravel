<?php

namespace Lineage\Console\Commands;

use Illuminate\Console\Command;
use Lineage\Console\Traits\UserWallets;
use Lineage\Exceptions\NotImplemented;

class CreateTradeRequest extends Command
{
    use UserWallets;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lineage:create-trade-request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a trade request between 2 addresses';

    /**
     * Execute the console command.
     *
     * 2-way payments (trade requests) are deferred until the /v1 endpoints
     * for them land, so this command no longer walks the wallet/asset
     * selection flow - it surfaces the deferral immediately.
     */
    public function handle(): int
    {
        try {
            \Lineage::createTradeRequest(
                otherPartyAddress: '',
                myAsset: null,
                myAddress: '',
                otherPartyAsset: null,
            );
        } catch (NotImplemented $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
