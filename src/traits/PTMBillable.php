<?php
/*
 *  mollie_interface - PTMBillable.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\traits;


use PTM\MollieInterface\contracts\SubscriptionBuilder;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\Repositories\FirstPaymentSubscriptionBuilder;

trait PTMBillable
{
    use isMollieCustomer;

    public function subscribe(Plan $plan, $subscribed_on, SubscriptionInterval $interval = SubscriptionInterval::MONTHLY, $forceConfirmationPayment=false): SubscriptionBuilder
    {
        if (!$this->needsFirstPayment()) {
            return \PTM\MollieInterface\Builders\SubscriptionBuilder::fromPlan($this, $plan, $interval)->subscribedOn($subscribed_on)->forceConfirmation($forceConfirmationPayment);
        }
        return FirstPaymentSubscriptionBuilder::fromPlan($this, $plan, $interval)->subscribedOn($subscribed_on);
    }

    /**
     * @param $identifier
     * @return bool
     */
    public function isSubscribed($identifier): bool
    {
        return $this->subscriptions()->where('subscribed_on', $identifier)->exists();
    }

    /**
     * @param Plan $plan
     * @param $subscribed_on
     * @param SubscriptionInterval $interval
     * @param bool $resetStartCycle
     * @param bool $forceConfirmationPayment
     * @return SubscriptionBuilder|Subscription|Redirect
     */
    public function updateOrSubscribe(Plan $plan, $subscribed_on, SubscriptionInterval $interval = SubscriptionInterval::MONTHLY, bool $resetStartCycle = true, $forceConfirmationPayment=false)
    {
        $subscription = $this->getSubscription($subscribed_on);
        if (!$subscription || $subscription->ends_at || !$subscription->mollie_subscription_id){
            if ($subscription && !$subscription->mollie_subscription_id){
                // Check if subscription already exists and the payment is still open...
                if (!$subscription->isActive()
                    && $subscription->plan_id === $plan->id
                && $subscription->getInterval()->getLetter() === $interval->getLetter()){
                    // Subscription is the same as existing...
                    $payment = $subscription->payments()->first();
                    if ($payment->mollie_payment_status = 'open'){
                        // Use old payment.
                        /**
                         * @var $mPay \Mollie\Api\Resources\Payment
                         */
                        $mPay = $payment->getMolliePayment();
                        // Check if merge is the same as...
                        if (!$mPay->metadata->merged
                            || (($mPay->metadata->merged === 1) === $this->isMerged())){
                            $checkout = $mPay->_links->checkout;
                            if ($mPay->isOpen() && $checkout){
                                return new Redirect($checkout->href);
                            }
                        }
                    }
                }
            }
            // Return new builder.
            return $this->subscribe($plan, $subscribed_on, $interval, $forceConfirmationPayment);
        }

        // Check plan before changing.
        if ($subscription->plan_id !== $plan->id){
            $subscription->changePlan($plan);
        }

        // Check interval before changing.
        if ($subscription->getInterval()->getLetter() !== $interval->getLetter()){
            $subscription->changeInterval($interval, $resetStartCycle);
        }
        return $subscription;
    }

    /**
     * Get all subscriptions associated with this model
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'billable');
    }

    /**
     * Get the subscription's payments.
     */
    public function payments()
    {
        return $this->morphMany(Payment::class, 'billable');
    }

    /**
     * @param $identifier
     * @return Subscription|null
     */
    public function getSubscription($identifier)
    {
        return $this->subscriptions()->orderByDesc('created_at')->firstWhere('subscribed_on', $identifier);
    }

    public function isMerged(){
        return $this->mollieCustomer->merge_subscriptions ?? false;
    }

    public function getMandates(){
        return $this->mollieCustomer->api()->mandates();
    }
    

}