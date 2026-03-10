<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('a_block_keypairs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('a_block_wallet_id');
            $table->string('name');
            $table->string('save');
            $table->string('nonce');
            $table->string('address');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['a_block_wallet_id', 'name']);
        });
    }
};
