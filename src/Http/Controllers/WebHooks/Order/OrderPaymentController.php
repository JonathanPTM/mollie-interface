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
            return response("No payment was found.", 404);
        }
        $order = Order::find($request->route('order'));
        if (!$order){
            return response("No order was found.", 403);
        }
        $localPayment = (new PaymentHandler($payment))->execute($order->getInterface());
        if ($payment->isPaid()){
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
            $payment->update();

            // Execute order
            if (!$order){
                Event::dispatch(new PaymentPaid($payment, $localPayment));
                return response("No order was found.", 402);
            }
            $order->confirm($localPayment);
            Event::dispatch(new PaymentPaid($payment, $localPayment));
        } else {
            Event::dispatch(new FirstPaymentFailed($payment));
        }
        return response("Ok", 200);
    }
}