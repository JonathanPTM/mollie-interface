<?php

namespace PTM\MollieInterface\jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PTM\MollieInterface\models\Order;

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
    public function handle(Order$order)
    {
        $builder = $this->builder;
        $builder->setInterface($order->getInterface());
        $builder->executeOrder();
    }

}