<?php

namespace PTM\MollieInterface\Interfaces;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use PTM\MollieInterface\contracts\PaymentBuilder;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\models\SubscriptionInterval;

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
    private function getPaymentPayload(PaymentBuilder $builder): array
    {
        return array_filter(array_merge([
            'sequenceType' => $builder->sequenceType,
            'cardToken'=>$builder->cardToken,
            'method'=>$builder->method,
            'customerId' => $builder->mollieCustomerId ?? $builder->owner->CustomerId(),
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

    public function createPayment(PaymentBuilder $builder){
        return mollie()->payments()->create($this->getPaymentPayload($builder));
    }

    public function paymentToDatabase($payment){
        return array_filter([
            'interface_id' => $payment->id,
            'payment_status' => $payment->status,
            'mandate_id' => $payment->mandateId,
            'currency' => $payment->amount->currency,
            'amount' => $payment->amount->value,
            'amount_refunded' => ($payment->amountRefunded ? $payment->amountRefunded->value : null),
            'amount_charged_back' => ($payment->amountChargedBack ? $payment->amountChargedBack->value : null)
        ]);
    }
    
    public function getRedirect($payment): Redirect
    {
        return new Redirect($payment->getCheckoutUrl());
    }

    public function createSubscription($owner, Subscription $subscription, $startNow=false) {
        return $owner
            ->CustomerAPI()
            ->createSubscription($this->getSubscriptionPayload($subscription, $startNow));
    }

    private function getSubscriptionPayload(Subscription $subscription, $startNow=false){
        $interval = $subscription->getInterval();
        return [
            'amount'=>$this->money_to_mollie_array($subscription->getAmount()),
            'interval'=>$interval->toMollie(),
            'startDate'=> ($startNow ? now()->format('Y-m-d') : Carbon::parse($subscription->cycle_ends_at)->format('Y-m-d')),
            'description'=>$subscription->getDiscriminator()." - ".$subscription->plan->description,
            'mandateId'=>$subscription->mandate_id ?? $subscription->billable->ptmCustomer()->where('interface', self::class)->first()->mandate_id,
            'webhookUrl'=>route('ptm_mollie.webhook.payment.subscription', ['subscription_id' => $subscription->id]),
            'metadata'=>[
                'id'=>$subscription->id,
                'subscribed_on'=>$subscription->subscribed_on,
                'billable'=>[
                    'type'=>$subscription->billable_type,
                    'id'=>$subscription->billable_id
                ]
            ]
        ];
    }

    public function makePaymentFromProvider($payment): array {
        $amountChargedBack = $payment->amountChargedBack
            ? (float)$payment->amountChargedBack->value
            : 0.0;

        $amountRefunded = $payment->amountRefunded
            ? (float)$payment->amountRefunded->value
            : 0.0;
        return [
            'mollie_payment_id' => $payment->id,
            'mollie_payment_status' => $payment->status,
            'currency' => $payment->amount->currency,
            'amount' => (float)$payment->amount->value,
            'amount_refunded' => $amountRefunded,
            'amount_charged_back' => $amountChargedBack,
            'mollie_mandate_id' => $payment->mandateId,
            'method'=> $payment->method
        ];
    }
    
    public function getPayment(Payment $payment){
        return mollie()->payments()->get($payment->interface_id);
    }

    public function createCustomer(Model $user, $override_options){
        $options = array_merge(
            method_exists($user, 'mollieCustomerFields')
                ? $user->mollieCustomerFields()
                : $this->mollieCustomerFields($user),
            $override_options
        );
        $customer = mollie()->customers()->create($options);
        return $customer;
    }

    public function mollieCustomerFields(Model $user)
    {
        return [
            'name'=> $user->name,
            'email' => $user->email
        ];
    }

    public function CustomerAPI($id){
        return mollie()->customers()->get($id);
    }

    public function updateSubscriptionAfterPayment(Subscription $subscription, $payment){
        $mollieSubscription = mollie()->subscriptions()->getForId($payment->customerId, $payment->subscriptionId);
        $subscription->updateCycle(Carbon::parse($payment->paidAt), Carbon::parse($mollieSubscription->nextPaymentDate));
        $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
        $payment->update();
    }

    public function getCycle(Subscription$subscription){
        $mollieSubscription = $subscription->billable->CustomerAPI($this)->getSubscription($subscription->interface_id);
        return SubscriptionInterval::fromMollie($mollieSubscription->interval);
    }

    public function cancelSubscription(Subscription$subscription){
        $subscription->billable->CustomerAPI($subscription->getInterface())->getSubscription($subscription->interface_id)->cancel();
    }

    public function getSubscription(Subscription $subscription, $customerId){
        return mollie()->subscriptions()->getForId($customerId, $subscription->interface_id);
    }
}