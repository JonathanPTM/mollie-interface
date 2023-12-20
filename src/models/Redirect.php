<?php

namespace PTM\MollieInterface\models;

class Redirect
{
    public function __construct(public string $to)
    {
    }
    public function run(){
        return redirect()->away($this->to);
    }
}