<?php

namespace PTM\MollieInterface\Builders;

use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\SubscriptionInterval;

class PaymentBuilder implements \PTM\MollieInterface\contracts\PaymentBuilder
{
    use \PTM\MollieInterface\traits\PaymentBuilder;

    private Plan $plan;

    public function __construct(?float $total)
    {
        $this->total = $total ?? 0;
        $this->taxPercentage = env('SUBSCRIPTION_TAX', 21);
    }

    public function setPlan(Plan$plan){
        $this->plan = $plan;
        $this->calculateTotal();
        return $this;
    }

    /**
     * @return Plan
     */
    public function getPlan(): Plan
    {
        return $this->plan;
    }

    public function calculateTotal(){
        // ToDo: recalculate total.
        if (!$this->plan) return;
        $this->total = $this->plan->mandatedAmountIncl($this->interval, $this->taxPercentage);
    }

    public function setRedirectURL(string $url){
        $this->redirectUrl = $url;
        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param SequenceType $sequenceType
     */
    public function setSequenceType(string $sequenceType): void
    {
        $this->sequenceType = $sequenceType;
    }

    /**
     * @param SubscriptionInterval $interval
     */
    public function setInterval(SubscriptionInterval $interval): void
    {
        $this->interval = $interval;
    }

    public function setTax(int $tax)
    {
        if ($tax === 0){
            $this->taxPercentage = 0;
            $this->calculateTotal();
            return $this;
        }
        $this->taxPercentage = ($tax > 1 ? $tax : ($tax > 0 ? ($tax * 100) : 0));
        $this->calculateTotal();
        return $this;
    }

    /**
     * @return Payment
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function create(){
        // Get payment variables
        $payload = $this->getMolliePayload();
        // Create mollie payment
        $this->molliePayment = mollie()->payments()->create($payload);
        // Connect payment to the subscription
        $payment = new Payment();
        $payment->fill($this->getPaymentPayload());
        // Return payment object
        return $payment;
    }
}