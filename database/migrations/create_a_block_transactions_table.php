<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lineage\Client;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('lineage_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('lineage_wallet_id');
            $table->enum('status', [
                Client::TRANSACTION_STATUS_PENDING,
                Client::TRANSACTION_STATUS_ACCEPTED,
                Client::TRANSACTION_STATUS_REJECTED
            ])->default(Client::TRANSACTION_STATUS_PENDING);
            $table->string('druid');
            $table->string('nonce');
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
