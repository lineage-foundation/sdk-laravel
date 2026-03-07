<?php

namespace IODigital\ABlockLaravel\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use IODigital\ABlockPHP\ABlockClient;
use IODigital\ABlockLaravel\AWallet;
use IODigital\ABlockLaravel\Console\Commands\CreateWalletForUser;
use IODigital\ABlockLaravel\Console\Commands\CreateKeypairForWallet;
use IODigital\ABlockLaravel\Console\Commands\CreateItem;
use IODigital\ABlockLaravel\Console\Commands\CheckBalance;
use IODigital\ABlockLaravel\Console\Commands\SendItemToAddress;
use IODigital\ABlockLaravel\Console\Commands\CreateTradeRequest;
use IODigital\ABlockLaravel\Console\Commands\GetPendingTransactions;
use IODigital\ABlockLaravel\Console\Commands\AcceptPendingTransaction;
use IODigital\ABlockLaravel\Console\Commands\CommandApp;
use IODigital\ABlockLaravel\Console\Commands\RejectPendingTransaction;

class ABlockServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ABlockClient::class, function (Application $app) {
            return new ABlockClient(
                computeHost: config('a-block.compute_host'),
                intercomHost: config('a-block.intercom_host'),
                storageHost: config('a-block.storage_host'),
            );
        });

        $this->app->bind('a-wallet', function () {
            return new AWallet(
                client: $this->app->make(ABlockClient::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/a-block.php' => config_path('a-block.php'),
        ], 'a-block-config');

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
