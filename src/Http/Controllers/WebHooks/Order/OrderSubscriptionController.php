<?php

namespace PTM\MollieInterface\Http\Controllers\WebHooks\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use PTM\MollieInterface\Events\FirstPaymentFailed;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\Events\SubscriptionPaymentFailed;
use PTM\MollieInterface\Http\Controllers\WebHooks\WebhookController;
use PTM\MollieInterface\models\Order;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\Repositories\Handlers\PaymentHandler;

class OrderSubscriptionController  extends WebhookController
{
    public function hooked(Request $request)
    {
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment) {
            return response("No payment was found.", 404);
        }
        $subscription = Subscription::find($request->route('subscriptionId'));
        if (!$subscription){
            return response("No subscription was found.", 403);
        }
        $localPayment = (new PaymentHandler($payment))->execute($subscription->getInterface());
        if ($payment->isPaid()){
            $subscription->getInterface()->updateSubscriptionAfterPayment($subscription, $payment);
        } else {
            Event::dispatch(new SubscriptionPaymentFailed($payment, $localPayment));
        }
        return response("Ok", 200);
    }
}