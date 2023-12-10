<?php

namespace PTM\MollieInterface\Interfaces;

use Carbon\Carbon;
use PTM\MollieInterface\contracts\PaymentBuilder;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\Subscription;

class MollieInterface implements PaymentProcessor
{

    /**
     * Create a Mollie Amount array from a Money object.
     *
     * @param float
     * @return array $array
     */
    private function money_to_mollie_array(float $money)
    {
        return [
            'currency' => "EUR",
            'value' => number_format($money, 2, '.', '')
        ];
    }

    /**
     * @param PaymentBuilder $builder
     * @return array
     */
    public function getPaymentPayload(PaymentBuilder $builder): array
    {
        return array_filter(array_merge([
            'sequenceType' => $builder->sequenceType,
            'cardToken'=>$builder->cardToken,
            'method'=>$builder->method,
            'customerId' => $builder->mollieCustomerId ?? $builder->owner->mollieCustomerId(),
            'description' => $builder->description,
            'amount' => $this->money_to_mollie_array($builder->total),
            'webhookUrl' => $builder->webhookUrl,
            'redirectUrl' => $builder->redirectUrl,
            'metadata' => [
                'owner' => [
                    'type' => $builder->owner->getMorphClass(),
                    'id' => $builder->owner->getKey(),
                ],
                'order_id' => $builder->order_id,
                'subscription' => $builder->subscriptionID,
                'merged' => $builder->merging
            ],
        ], $builder->options));
    }

    public function createPayment($payload){
        return mollie()->payments()->create($payload);
    }

    public function paymentToDatabase($payment){
        return array_filter([
            'mollie_payment_id' => $payment->id,
            'mollie_payment_status' => $payment->status,
            'mollie_mandate_id' => $payment->mandateId,
            'currency' => $payment->amount->currency,
            'amount' => $payment->amount->value,
            'amount_refunded' => ($payment->amountRefunded ? $payment->amountRefunded->value : null),
            'amount_charged_back' => ($payment->amountChargedBack ? $payment->amountChargedBack->value : null)
        ]);
    }
    
    public function getRedirect($payment): Redirect
    {
        return new Redirect($payment->redirectUrl);
    }

    public function createSubscription($owner, $payload) {
        $owner
            ->CustomerAPI()
            ->createSubscription($payload);
    }

    public function getSubscriptionPayload(Subscription $subscription, $startNow=false){
        $interval = $subscription->getInterval();
        return [
            'amount'=>$this->money_to_mollie_array($subscription->getAmount()),
            'interval'=>$interval->toMollie(),
            'startDate'=> ($startNow ? now()->format('Y-m-d') : Carbon::parse($subscription->cycle_ends_at)->format('Y-m-d')),
            'description'=>($subscription->subscribed_on ?? $subscription->id)." - ".$subscription->plan->description,
            'mandateId'=>$subscription->mollie_mandate_id ?? $subscription->billable->mollieCustomer->mollie_mandate_id,
            'webhookUrl'=>route('ptm_mollie.webhook.payment.subscription', ['subscription_id' => $subscription->id]),
            'metadata'=>[
                'subscribed_on'=>$subscription->subscribed_on,
                'billable'=>[
                    'type'=>$subscription->billable_type,
                    'id'=>$subscription->billable_id
                ]
            ]
        ];
    }
}