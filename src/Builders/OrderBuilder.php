<?php

namespace PTM\MollieInterface\Builders;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use PTM\MollieInterface\contracts\PaymentBuilder;
use PTM\MollieInterface\contracts\SubscriptionBuilder;
use PTM\MollieInterface\jobs\createSubscriptionAction;
use PTM\MollieInterface\models\Order;

class OrderBuilder implements \PTM\MollieInterface\contracts\OrderBuilder
{
    public Order $order;
    public ?PaymentBuilder $payment = null;

    public function __construct()
    {
        $this->order = new Order();
    }

    public function addAction(ShouldQueue $job)
    {
        $this->order->addAction($job);
    }

    public function setActions(?array $actions)
    {
        $this->order->setActions($actions);
    }

    public function setBillable(Model $billable)
    {
        $this->order->billable()->associate($billable);
    }

    public function setPayment(PaymentBuilder$builder)
    {
        $builder->setWebhookUrl(route('ptm_mollie.webhook.order.payment', ['order'=>$this->order->id]));
        $this->payment = $builder;
    }

    public function setSubscription(SubscriptionBuilder $builder)
    {
        $this->addAction(new createSubscriptionAction($builder->__serialize()));
        if ($builder->mustConfirmPayment()){
            // Set Payment.
            $this->setPayment($builder->getPaymentBuilder());
        }
    }

    /**
     * @return Order|string|null
     */
    public function build()
    {
        $this->order->save();

        // if there is a payment, create it. Store it. And return redirect.
        if ($this->payment !== null){
            $payment = $this->payment->create();
            $payment->paymentable()->associate($this->order);
            $payment->save();
            return $this->payment->redirect();
        }
        return $this->order;
    }
}