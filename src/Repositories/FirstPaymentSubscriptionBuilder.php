<?php
/*
 *  mollie_interface - FirstPaymentSubscriptionBuilder.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Types\SequenceType;
use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\contracts\SubscriptionBuilder;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\traits\PaymentBuilder;

class FirstPaymentSubscriptionBuilder implements SubscriptionBuilder
{
    use PaymentBuilder;

    private $thread;

    public function __construct(Model $owner, float $total, string $description, array $options = [], ?Plan $plan = null)
    {
        $this->owner = $owner;
        $this->total = $total;
        $this->options = $options;
        $this->description = $description;
        $this->taxPercentage = config('ptm_subscription.tax', 21);
        $this->interval = SubscriptionInterval::MONTHLY;
        $this->sequenceType = SequenceType::SEQUENCETYPE_FIRST;
        $this->forceConfirmationPayment = true;
        $this->redirectUrl = null;
        if ($plan) $this->plan = $plan;
    }

    public static function fromPlan(Model $billable, Plan $plan, array $options = [])
    {
        return new self($billable, $plan->mandatedAmountIncl(SubscriptionInterval::MONTHLY, env('SUBSCRIPTION_TAX', 21)), $plan->description, $options, $plan);
    }

    public function create()
    {
        // Create subscription entry
        $subscription = $this->buildSubscription();

        // Get payment variables
        $payload = $this->getMolliePayload();
        // Create mollie payment
        $this->molliePayment = Mollie::api()->payments()->create($payload);
        // Connect payment to the subscription
        $subscription->payments()->create($this->getPaymentPayload());

        return $this->molliePayment;
    }

    /**
     * Create subscription model and mollie instance
     * @return mixed
     */
    private function buildSubscription()
    {
        $subscription = $this->owner->subscriptions()->where('subscribed_on', $this->thread)->where('plan_id', $this->plan->id ?? 0)->whereNull('ends_at')->first();
        if (!$subscription) {
            $subscription = $this->owner->subscriptions()->create([
                'subscribed_on' => $this->thread,
                'plan_id' => $this->plan->id ?? 0,
                'tax_percentage' => $this->taxPercentage ?? 0,
                'ends_at' => null,
                'cycle_started_at' => now(),
                'cycle_ends_at' => $this->interval->nextDate()
            ]);
        }
        $this->subscriptionID = $subscription->id;
        if (!$this->redirectUrl) $this->redirectUrl = route('ptm_mollie.redirect.subscription', ['id' => $subscription->id]);
        $this->webhookUrl = route('ptm_mollie.webhook.payment.first', ['subscription_id' => $subscription->id]);

        return $subscription;
    }

    public function nextPaymentAt(\Carbon\Carbon $nextPaymentAt)
    {
        // TODO: Implement nextPaymentAt() method.
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
        $base_price = ($this->total / (100 + $this->taxPercentage)) * 100;
        if ($tax === 0){
            $this->taxPercentage = 0;
            $this->total = $base_price;
            return $this;
        }
        $this->taxPercentage = ($tax > 1 ? $tax : ($tax > 0 ? ($tax * 100) : 0));
        $this->total = $base_price * (1 + ($this->taxPercentage / 100));
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

    public function setInterval(SubscriptionInterval $interval)
    {
        $this->interval = $interval;
        if ($this->plan) $this->total = $this->plan->mandatedAmountIncl($interval, $this->taxPercentage);
        return $this;
    }

    public function forceConfirmation(bool $enabled)
    {
        // Not needed, FirstPaymentSubscription has always forced Confirmation.
    }
}