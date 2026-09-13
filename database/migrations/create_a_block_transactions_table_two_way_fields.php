<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('lineage_transactions', function (Blueprint $table) {
            $table->json('encrypted_half')->nullable()->after('content');
            $table->json('sender_expectation')->nullable()->after('encrypted_half');
            $table->json('receiver_expectation')->nullable()->after('sender_expectation');
            $table->string('mempool_host')->nullable()->after('receiver_expectation');
        });
    }

    public function down(): void
    {
        Schema::table('lineage_transactions', function (Blueprint $table) {
            $table->dropColumn(['encrypted_half', 'sender_expectation', 'receiver_expectation', 'mempool_host']);
        });
    }
};
