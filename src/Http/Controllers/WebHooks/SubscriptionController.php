<?php
/*
 *  mollie_interface - SubscriptionController.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
 *  The software is protected by copyright laws and international copyright treaties,
 *  as well as other intellectual property laws and treaties. The software is licensed, not sold.
 *  You are not allowed to resell, claim ownership of, or modify the software in any way.
 *  You are also prohibited from distributing the software to any other entities, even for educational purposes.
 *  Any violation of this agreement will result in legal action being taken against you. The original developer,
 *  PTMDevelopment, may fine you up to 5000 euros per file and per week until the changes,
 *  claims, or distribution is reversed.
 *  PTMDevelopment reserves the right to add additional compensation at any later date if deemed necessary.
 *
 *  By using or installing the software, you automatically agree to the terms of this agreement.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

namespace PTM\MollieInterface\Http\Controllers\WebHooks;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\Events\SubscriptionConfirmed;
use PTM\MollieInterface\Events\SubscriptionPaymentFailed;
use PTM\MollieInterface\jobs\MergeSubscriptions;
use PTM\MollieInterface\models\MollieCustomer;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\Repositories\MollieSubscriptionBuilder;

class SubscriptionController extends WebhookController
{
    public function hooked(Request $request){
        Log::debug("Heeft dit er mee te maken? ".$request->url());
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment){
            return new Response(null, 404);
        }
        if ((!isset($payment->subscriptionId) || !isset($payment->customerId)) && (!$request->has('fcp') || $request->get('fcp') !== 'true')) {
            return \response('Subscription identifier parameter not found.', 504);
        }
        DB::beginTransaction();
        $mollieSubscription = null;
        $query = $request->query;
        if (!$query->has('fcp') || $query->get('fcp') !== 'true') {
            $mollieSubscription = Mollie::api()->subscriptions()->getForId($payment->customerId, $payment->subscriptionId);
            /**
             * @var $localSubscription Subscription
             */
            $localSubscription = Subscription::where('mollie_subscription_id', $mollieSubscription->id)->first();
        } else {
            $localSubscription = Subscription::find($request->get('subscription_id'));
            if ($localSubscription && $localSubscription->mollie_subscription_id) $mollieSubscription = Mollie::api()->subscriptions()->getForId($payment->customerId, $localSubscription->mollie_subscription_id);
        }

        // Merged subscriptions handler...
        Log::debug("Vars are", [$query->get('merging') === 'true', $localSubscription->is_merged]);
        if (($query->has('merging') && $query->get('merging') === 'true') || $localSubscription->is_merged) {
            return $this->mergeHandler($request, $payment, $localSubscription);
        }
        // Make payment
        $localPayment = Payment::makeFromMolliePayment($payment, $localSubscription);

        if ($payment->isPaid()){
            if ($query->has('fcp') && $query->get('fcp') === 'true' && !$mollieSubscription){
                // Subscription needs to be created!
                $mollieSubscription = (new MollieSubscriptionBuilder($localSubscription, $localSubscription->billable))->execute();
                Event::dispatch(new SubscriptionConfirmed($localSubscription));
            }
            // Update subscription cycle...
            $cycle = $localSubscription->getCycle();
            $payed_at = Carbon::parse($payment->paidAt);
            $next = $cycle->nextDate($payed_at);
            Log::debug("SubscriptionController:76 Subscription({$localSubscription->id}) cycle change: {$payed_at->toString()} + {$next->toString()}");
            if ($mollieSubscription) $localSubscription->updateCycle($payed_at, $mollieSubscription->nextPaymentDate);
            Event::dispatch(new PaymentPaid($payment, $localPayment, $mollieSubscription));
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
            $payment->update();
        } else {
            if ($query->has('fcp') && $query->get('fcp') === 'true'){
                if ($localSubscription && !$mollieSubscription) $localSubscription->delete();
            }
            Event::dispatch(new SubscriptionPaymentFailed($payment, $localPayment, $mollieSubscription));
        }
        DB::commit();
        return new \Illuminate\Http\Response(null, 200);
    }

    private function mergeHandler($request, $payment, $localSubscription){
        Log::debug("Switching handler to MERGE handler.");
        $query = $request->query;
        $customer = MollieCustomer::where('mollie_customer_id', $payment->customerId)->first();
        $offset = null;
        if ($query->has('offset') && $query->get('offset') !== 'false'){
            $offset = $query->get('offset');
        }
        // Make payment
        $localPayment = Payment::makeFromMolliePayment($payment, $localSubscription, [], [], $offset);
        if ($payment->isPaid()){
            Event::dispatch(new PaymentPaid($payment, $localPayment, null, true, $offset));
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after', ['merged'=>true]);
            $payment->update();
            DB::commit();
            MergeSubscriptions::dispatch($customer)->afterResponse()->onQueue('developmentBus');
            return response()->json(['success'=>true,'message'=>'Merged subscription has been done ;)']);
        } else {
            Event::dispatch(new SubscriptionPaymentFailed($payment, $localPayment, null));
        }
        DB::commit();
        return new \Illuminate\Http\Response(null, 200);
    }
}