<?php

namespace PTM\MollieInterface\Facade;

use PTM\MollieInterface\Builders\Builder;

class PTM extends Builder
{
    public function __construct()
    {
        parent::__construct(true);
    }

    public function version(){
        return "v1.0";
    }
}