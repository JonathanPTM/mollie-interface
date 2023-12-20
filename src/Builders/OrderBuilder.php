<?php

namespace PTM\MollieInterface\Builders;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PTM\MollieInterface\Builders\SubscriptionBuilder as SubBuilder;
use PTM\MollieInterface\contracts\PaymentBuilder;
use PTM\MollieInterface\contracts\SubscriptionBuilder;
use PTM\MollieInterface\Events\OrderBuild;
use PTM\MollieInterface\jobs\changePaymentMethod;
use PTM\MollieInterface\models\Order;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\Redirect;

class OrderBuilder extends Builder implements \PTM\MollieInterface\contracts\OrderBuilder
{
    public Order $order;
    public ?PaymentBuilder $payment = null;
    private $paymentSaver;

    public function __construct()
    {
        parent::__construct();
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
        $builder->setOrderID($this->order->id);
        $builder->setWebhookUrl(route('ptm_mollie.webhook.order.payment', ['order'=>$this->order->id]));
        $this->payment = $builder;
    }


    /**
     * Create a subscription builder.
     * @param Plan $plan
     * @return SubBuilder
     */
    public function buildSubscriptionFromPlan(Plan $plan){
        $owner = $this->order->billable;
        $builder = SubBuilder::fromPlan($owner, $plan);
        $builder->forceConfirmation($owner->needsFirstPayment());
        return $builder;
    }

    /**
     * Provide a SubscriptionBuilder if you want to create a subscription after the order has been completed.
     * This function will add the SubscriptionBuilder in a job to the Actions of this order.
     * @param SubscriptionBuilder $builder
     * @param bool $confirm if you want a confirmation payment.
     * @return void
     */
    public function setSubscription(SubscriptionBuilder $builder, bool $confirm=false): void
    {
        if ($builder->mustConfirmPayment() || $confirm){
            // Set Payment.
            $this->setPayment($builder->getPaymentBuilder());
        }
    }

    /**
     * If you would like to run your own logic for saving the payment, then provide a callback handler. cb($payment){...logic}
     * @param callable $callback
     * @return void
     */
    public function setPaymentSaver(callable $callback)
    {
        $this->paymentSaver = $callback;
    }

    /**
     * Create the order and run any required logic, like payment creation.
     * @return Order|Redirect
     */
    public function build()
    {
        DB::beginTransaction();
        $this->order->interface = $this->exportInterface();
        $this->order->save();

        // Trigger order event.
        Event::dispatch(new OrderBuild($this->order));

        // if there is a payment, create it. Store it. And return redirect.
        if ($this->payment !== null){
            $payment = $this->payment->create();
            if ($this->paymentSaver && is_callable($this->paymentSaver)){
                call_user_func($this->paymentSaver, $payment);
            } else {
                $payment->save();
            }
            DB::commit();
            return $this->payment->redirect();
        }
        DB::commit();
        return $this->order;
    }
}