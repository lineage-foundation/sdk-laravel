<?php

namespace IODigital\ABlockLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class AWalletFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'a-wallet';
    }
}
