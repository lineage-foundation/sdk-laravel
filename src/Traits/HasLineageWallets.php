<?php

declare(strict_types=1);

namespace Lineage\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lineage\Models\LineageWallet;

trait HasLineageWallets
{
    public function lineageWallets(): MorphMany
    {
        return $this->morphMany(LineageWallet::class, 'owner');
    }

    public function openDefaultLineageWallet(string $passPhrase): bool
    {
        $wallet = $this->lineageWallets()->default()->first();
        return \Lineage::setActive($wallet, $passPhrase);
    }
}
