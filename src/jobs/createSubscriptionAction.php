<?php

namespace PTM\MollieInterface\jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class createSubscriptionAction implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $builder)
    {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $builder = $this->builder;
        $builder->executeOrder();
    }

}