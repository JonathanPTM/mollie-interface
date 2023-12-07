<?php

class TestController
{
    public function createNewOrder(){
        $builder = new \PTM\MollieInterface\Builders\OrderBuilder();
        $builder->setBillable();
        $builder->setSubscription();
    }
}