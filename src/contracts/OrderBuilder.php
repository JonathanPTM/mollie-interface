<?php

namespace PTM\MollieInterface\contracts;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

interface OrderBuilder
{
    public function addAction(ShouldQueue $job);
    public function setActions(?array $actions);
    public function setBillable(Model $billable);
    public function setPayment(PaymentBuilder$builder);
    public function setSubscription(SubscriptionBuilder $builder);
    public function build();
}