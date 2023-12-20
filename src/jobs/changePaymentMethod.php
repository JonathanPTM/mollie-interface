<?php

namespace PTM\MollieInterface\jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PTM\MollieInterface\models\Order;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\Repositories\Handlers\PaymentMethodHandler;

class changePaymentMethod implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Subscription $subscription)
    {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Order$order)
    {
        if (!($order->confirmatable instanceof Payment)) {
            Log::debug("Order doesn;t have payment, $order->id");
            return;
        }
        $payment = $order->getInterface()->getPayment($order->confirmatable);
        if (!$payment) {
            Log::debug("No mollie payment found for, $order->id");
            return;
        }
        (new PaymentMethodHandler($payment, $this->subscription))->execute($order->getInterface());
    }

}