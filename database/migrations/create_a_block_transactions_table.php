<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IODigital\ABlockPHP\ABlockClient;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('a_block_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('a_block_wallet_id');
            $table->enum('status', [
                ABlockClient::TRANSACTION_STATUS_PENDING,
                ABlockClient::TRANSACTION_STATUS_ACCEPTED,
                ABlockClient::TRANSACTION_STATUS_REJECTED
            ])->default(ABlockClient::TRANSACTION_STATUS_PENDING);
            $table->string('druid');
            $table->string('nonce');
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
