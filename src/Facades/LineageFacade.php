<?php

namespace Lineage\Facades;

use Illuminate\Support\Facades\Facade;

class LineageFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'lineage';
    }
}
