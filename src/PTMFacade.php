<?php

namespace PTM\MollieInterface;

use Illuminate\Support\Facades\Facade;

class PTMFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ptm';
    }
}