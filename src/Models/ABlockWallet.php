<?php

namespace IODigital\ABlockLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class ABlockWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'default',
        'name',
        'master_key_encrypted_base64',
        'nonce_hex',
        'owner_type',
        'owner_id',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function keypairs(): HasMany
    {
        return $this->hasMany(ABlockKeypair::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ABlockTransaction::class, 'a_block_wallet_id');
    }

    public function scopeDefault(Builder $query): void
    {
        $query->where('default', true);
    }

    public function setAsDefault(): void {}

    protected static function booted(): void
    {
        static::created(function (ABlockWallet $wallet) {
            $ids = $wallet->owner->aBlockWallets->where('id', '!=', $wallet->id)->pluck('id');
            self::whereIn('id', $ids)->update([
                'default' => false
            ]);
        });
    }
}
