<?php

namespace PTM\MollieInterface\Http\Controllers\WebHooks\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use PTM\MollieInterface\Events\FirstPaymentFailed;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\Http\Controllers\WebHooks\WebhookController;
use PTM\MollieInterface\models\Order;
use PTM\MollieInterface\Repositories\Handlers\PaymentHandler;

class OrderPaymentController  extends WebhookController
{
    public function hooked(Request $request)
    {
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment) {
            return response()->setStatusCode(404, "No payment was found.");
        }
        $localPayment = (new PaymentHandler($payment))->execute();
        if ($payment->isPaid()){
            Event::dispatch(new PaymentPaid($payment, $localPayment));
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
            $payment->update();

            // Execute order
            $order = Order::find($request->route('order'));
            if (!$order){
                return response()->setStatusCode(404, "No order was found.");
            }
            $order->confirm($localPayment);
        } else {
            Event::dispatch(new FirstPaymentFailed($payment));
        }
        return response()->setStatusCode(200, "ok.");
    }
}