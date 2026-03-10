<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('a_block_wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->boolean('default')->default(true);
            $table->string('name');
            $table->string('master_key_encrypted_base64');
            $table->string('nonce_hex');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_type', 'owner_id', 'name']);
        });
    }
};
