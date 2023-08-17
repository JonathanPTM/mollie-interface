<?php

namespace PTM\MollieInterface\Http\Controllers\WebHooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\jobs\MergeSubscriptions;
use PTM\MollieInterface\models\MollieCustomer;
use PTM\MollieInterface\models\Subscription;

class MergeController extends WebhookController
{
    public function hooked(Request $request){
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment){
            return response('Not found', 404);
        }
        $query = $request->query;
        $localSubscription = Subscription::find($request->get('subscription_id'));
        $customer = MollieCustomer::where('mollie_customer_id', $payment->customerId)->first();
        $offset = null;
        if ($query->has('offset') && $query->get('offset') !== 'false'){
            $offset = $query->get('offset');
        }
        DB::beginTransaction();
        // Make payment
        $localPayment = $this->getPayment($localSubscription, $payment, $offset);
        if ($payment->isPaid()){
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after', ['merging'=>true,'differ'=>true]);
            $payment->update();
            $localPayment->update([
                'mollie_payment_status' => $payment->status
            ]);
            DB::commit();
            MergeSubscriptions::dispatch($customer)
                ->onQueue('developmentBus');
            Event::dispatch(new PaymentPaid($payment, $localPayment, null));
            return response()->json(['success'=>true,'message'=>'Merged subscription has been done ;)']);
        }
        DB::commit();
        return new \Illuminate\Http\Response(null, 200);
    }
}