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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\Events\SubscriptionPaymentFailed;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Subscription;

class SubscriptionController extends WebhookController
{
    public function hooked(Request $request){
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment){
            return new Response(null, 404);
        }
        if (!isset($payment->subscriptionId) || !isset($payment->customerId)) {
            return response()->setStatusCode(504, 'Subscription identifier parameter not found.');
        }
        $mollieSubscription = Mollie::api()->subscriptions()->getForId($payment->customerId, $payment->subscriptionId);
        /**
         * @var $localSubscription Subscription
         */
        $localSubscription = Subscription::where('mollie_subscription_id', $mollieSubscription->id)->first();
        // Make payment
        $localPayment = Payment::makeFromMolliePayment($payment, $localSubscription);

        if ($payment->isPaid()){
            // Update subscription cycle...
            $cycle = $localSubscription->getCycle();
            $payed_at = Carbon::parse($payment->paidAt);
            $localSubscription->update([
                'cycle_started_at'=>$payed_at,
                'cycle_ends_at'=>$cycle->nextDate($payed_at)
            ]);
            Event::dispatch(new PaymentPaid($payment, $localPayment, $mollieSubscription));
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
            $payment->update();
        } else {
            Event::dispatch(new SubscriptionPaymentFailed($payment, $localPayment, $mollieSubscription));
        }
        return new \Illuminate\Http\Response(null, 200);
    }
}