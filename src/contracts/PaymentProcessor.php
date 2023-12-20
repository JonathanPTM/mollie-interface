<?php

namespace PTM\MollieInterface\contracts;

use Illuminate\Database\Eloquent\Model;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\Subscription;

interface PaymentProcessor
{
    public function createPayment(PaymentBuilder $builder);
    public function paymentToDatabase($payment);
    public function getRedirect($payment): Redirect;
    public function createSubscription($owner, Subscription $subscription, $startNow=false);
    public function makePaymentFromProvider($payment): array;
    public function getPayment(Payment $payment);
    public function createCustomer(Model $user, $override_options);
    public function CustomerAPI($id);
    public function updateSubscriptionAfterPayment(Subscription $subscription, $payment);
    public function getCycle(Subscription$subscription);
    public function cancelSubscription(Subscription$subscription);
}