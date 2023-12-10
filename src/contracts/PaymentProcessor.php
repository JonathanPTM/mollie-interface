<?php

namespace PTM\MollieInterface\contracts;

use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\Subscription;

interface PaymentProcessor
{
    public function createPayment($payload);
    public function paymentToDatabase($payment);
    public function getRedirect($payment): Redirect;
    public function getPaymentPayload(PaymentBuilder $builder): array;
    public function createSubscription($owner, $payload);
    public function getSubscriptionPayload(Subscription $subscription, $startNow=false);
}