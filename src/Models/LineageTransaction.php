<?php

namespace Lineage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineageTransaction extends Model
{
    use HasFactory;

    protected $table = 'lineage_transactions';

    protected $fillable = [
        'lineage_wallet_id',
        'status',
        'druid',
        'nonce',
        'content',
        'encrypted_half',
        'sender_expectation',
        'receiver_expectation',
        'mempool_host',
    ];

    protected $casts = [
        'encrypted_half' => 'array',
        'sender_expectation' => 'array',
        'receiver_expectation' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LineageWallet::class, 'lineage_wallet_id');
    }
}
