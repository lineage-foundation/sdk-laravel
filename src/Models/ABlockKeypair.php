<?php

namespace IODigital\ABlockLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ABlockKeypair extends Model
{
    use HasFactory;

    protected $fillable = [
        'a_block_wallet_id',
        'name',
        'nonce',
        'save',
        'address',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ABlockWallet::class, 'a_block_wallet_id');
    }
}
