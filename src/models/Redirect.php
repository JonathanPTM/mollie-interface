<?php

namespace PTM\MollieInterface\models;

class Redirect
{
    private $endpoint;
    public function __construct(string $to)
    {
        $this->endpoint = $to;
    }
    public function run(){
        return redirect()->away($this->endpoint);
    }
}