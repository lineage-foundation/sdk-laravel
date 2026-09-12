<?php

namespace Lineage\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lineage\Client;
use Lineage\Lineage;
use Lineage\Console\Commands\CreateWalletForUser;
use Lineage\Console\Commands\CreateKeypairForWallet;
use Lineage\Console\Commands\CreateItem;
use Lineage\Console\Commands\CheckBalance;
use Lineage\Console\Commands\SendItemToAddress;
use Lineage\Console\Commands\CreateTradeRequest;
use Lineage\Console\Commands\GetPendingTransactions;
use Lineage\Console\Commands\AcceptPendingTransaction;
use Lineage\Console\Commands\CommandApp;
use Lineage\Console\Commands\RejectPendingTransaction;

class LineageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/lineage.php', 'lineage');

        $this->app->singleton(Client::class, function (Application $app) {
            return new Client(
                mempoolHost: config('lineage.mempool_host'),
                storageHost: config('lineage.storage_host'),
                apiKey: config('lineage.api_key'),
            );
        });

        $this->app->singleton('lineage', function (Application $app) {
            return new Lineage(
                client: $app->make(Client::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/lineage.php' => config_path('lineage.php'),
        ], 'lineage-config');

        $this->loadMigrationsFrom(
            __DIR__ . '/../../database/migrations'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateWalletForUser::class,
                CreateKeypairForWallet::class,
                CreateItem::class,
                CheckBalance::class,
                SendItemToAddress::class,
                CreateTradeRequest::class,
                GetPendingTransactions::class,
                AcceptPendingTransaction::class,
                CommandApp::class,
                RejectPendingTransaction::class
            ]);
        }
    }
}
