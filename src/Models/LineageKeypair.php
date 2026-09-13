<?php

namespace Lineage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineageKeypair extends Model
{
    use HasFactory;

    protected $table = 'lineage_keypairs';

    protected $fillable = [
        'lineage_wallet_id',
        'name',
        'nonce',
        'save',
        'address',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(LineageWallet::class, 'lineage_wallet_id');
    }
}
