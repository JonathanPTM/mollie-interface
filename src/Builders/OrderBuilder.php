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

    /**
     * Add an executable laravel job that will be run after the order is completed.
     * @param ShouldQueue $job
     * @return void
     */
    public function addAction(ShouldQueue $job)
    {
        $this->order->addAction($job);
    }

    /**
     * Set the array of executable laravel jobs that are serializable.
     * @param array|null $actions
     * @return void
     */
    public function setActions(?array $actions)
    {
        $this->order->setActions($actions);
    }

    /**
     * Set the parent model of this order.
     * Most of the time this will be the billable like a use or company.
     * @param Model $billable
     * @return void
     */
    public function setBillable(Model $billable)
    {
        $this->order->billable()->associate($billable);
    }

    /**
     * Provide a PaymentBuilder if you wish to make this order a one-time payment.
     * After the provided payment builder has been paid, this order will be executed.
     * @param PaymentBuilder $builder
     * @return void
     */
    public function setPayment(PaymentBuilder$builder)
    {
        $builder->setWebhookUrl(route('ptm_mollie.webhook.order.payment', ['order'=>$this->order->id]));
        $this->payment = $builder;
    }

    /**
     * Provide a SubscriptionBuilder if you want to create a subscription after the order has been completed.
     * This function will add the SubscriptionBuilder in a job to the Actions of this order.
     * @param SubscriptionBuilder $builder
     * @return void
     */
    public function setSubscription(SubscriptionBuilder $builder)
    {
        $this->addAction(new createSubscriptionAction($builder->__serialize()));
        if ($builder->mustConfirmPayment()){
            // Set Payment.
            $this->setPayment($builder->getPaymentBuilder());
        }
    }

    /**
     * Create the order and run any required logic, like payment creation.
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