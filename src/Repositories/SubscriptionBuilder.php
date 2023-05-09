<?php
/*
 *  mollie_interface - SubscriptionBuilder.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Types\SequenceType;
use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\traits\PaymentBuilder;

class SubscriptionBuilder implements \PTM\MollieInterface\contracts\SubscriptionBuilder
{
    use PaymentBuilder;
    private $thread;
    public function __construct(Model $owner, float $total, string $description, array $options = [], ?Plan $plan = null)
    {
        $this->owner = $owner;
        $this->total = $total;
        $this->options = $options;
        $this->description = $description;
        $this->taxPercentage = 21;
        $this->interval = SubscriptionInterval::MONTHLY;
        $this->sequenceType = SequenceType::SEQUENCETYPE_ONEOFF;
        $this->forceConfirmationPayment = false;
        $this->redirectUrl = url('');
        if ($plan) $this->plan = $plan;
    }

    public static function fromPlan(Model $billable, Plan $plan, array $options = [])
    {
        return new self($billable, $plan->mandatedAmountIncl(SubscriptionInterval::MONTHLY, env('SUBSCRIPTION_TAX', 21)), $plan->description, $options, $plan);
    }

    public function create()
    {
        if (!$this->owner->mollieCustomer || !$this->owner->mollieCustomer->mollie_mandate_id) {
            throw new \Exception("Mollie customer doesn't have mandateID!");
        }
        // Create subscription entry
        $subscription = $this->buildSubscription();

        // Check if confirmation payment is required
        if ($this->forceConfirmationPayment){
            // Get payment variables
            $payload = $this->getMolliePayload();
            // Create mollie payment
            $this->molliePayment = Mollie::api()->payments()->create($payload);
            // Connect payment to the subscription
            $subscription->payments()->create($this->getPaymentPayload());
            // Return payment object
            return $this->molliePayment;
        }
        // Or just create subscription using mandate
        return (new MollieSubscriptionBuilder($subscription, $this->owner))->execute();
    }

    private function buildSubscription(){
        $subscription = $this->owner->subscriptions()->create([
            'subscribed_on' => $this->thread,
            'plan_id' => $this->plan->id ?? 0,
            'tax_percentage' => $this->taxPercentage ?? 0,
            'ends_at' => null,
            'cycle_started_at' => now(),
            'cycle_ends_at' => $this->interval->nextDate()
        ]);
        $this->subscriptionID = $subscription->id;
        $this->webhookUrl = route('ptm_mollie.webhook.payment.subscription', ['subscription_id' => $subscription->id, 'fcp'=>$this->forceConfirmationPayment ? 'true' : 'false']);
        return $subscription;
    }

    public function subscribedOn($identifier)
    {
        $this->thread = $identifier;
        return $this;
    }

    public function redirect(): ?string
    {
        if ($this->molliePayment) return $this->molliePayment->redirectUrl;
        return null;
    }

    public function setTax(int $tax)
    {
        $this->taxPercentage = ($tax > 1 ? $tax : ($tax > 0 ? ($tax * 100) : 0));
        return $this;
    }

    public function setRedirectURL(string $url){
        $this->redirectUrl = $url;
        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    public function forceConfirmation(bool $enabled)
    {
        $this->forceConfirmationPayment = $enabled;
        return $this;
    }

    public function setInterval(SubscriptionInterval $interval)
    {
        $this->interval = $interval;
        if ($this->plan) $this->total = $this->plan->mandatedAmountIncl($interval, $this->taxPercentage ?? env('SUBSCRIPTION_TAX', 21));
        return $this;
    }

    public function nextPaymentAt(Carbon $nextPaymentAt)
    {
        // TODO: Implement nextPaymentAt() method.
    }
}