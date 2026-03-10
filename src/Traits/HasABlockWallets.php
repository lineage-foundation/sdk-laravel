<?php

declare(strict_types=1);

namespace IODigital\ABlockLaravel\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use IODigital\ABlockLaravel\Models\ABlockWallet;
use AWallet;

trait HasABlockWallets
{
    public function aBlockWallets(): MorphMany
    {
        return $this->morphMany(ABlockWallet::class, 'owner');
    }

    public function openDefaultABlockWallet(string $passPhrase): bool
    {
        $wallet = $this->aBlockWallets()->default()->first();
        return AWallet::setActive($wallet, $passPhrase);
    }
}
