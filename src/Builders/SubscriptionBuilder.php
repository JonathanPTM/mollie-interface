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

namespace PTM\MollieInterface\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\models\Order;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\Repositories\MollieSubscriptionBuilder;

class SubscriptionBuilder extends Builder implements \PTM\MollieInterface\contracts\SubscriptionBuilder
{
    use \PTM\MollieInterface\traits\SubscriptionBuilder;
    private $thread;
    public PaymentBuilder $builder;
    private Plan $plan;
    public function __construct(Model $owner = null, float $total = 0, string $description = "", array $options = [], ?Plan $plan = null)
    {
        parent::__construct();
        $builder = new PaymentBuilder($total);
        $builder->owner = $owner;
        $this->owner = $owner;
        $builder->total = $total;
        $builder->options = $options;
        $builder->description = $description;
        $builder->merging = $owner->isMerged();
        $builder->taxPercentage = config('ptm_subscription.tax', 21);
        $builder->interval = SubscriptionInterval::MONTHLY;
        $builder->sequenceType = SequenceType::SEQUENCETYPE_ONEOFF;
        $this->forceConfirmationPayment = false;
        $builder->redirectUrl = url('');
        if ($plan) $builder->setPlan($plan);
        $builder->setPaymentable($this);
        $this->builder = $builder;
    }

    /**
     * @return PaymentBuilder
     */
    public function getPaymentBuilder(): PaymentBuilder
    {
        return $this->builder;
    }

    public static function fromPlan(Model $billable, Plan $plan, SubscriptionInterval $interval = SubscriptionInterval::MONTHLY, array $options = [])
    {
        return new self($billable, $plan->mandatedAmountIncl($interval, env('SUBSCRIPTION_TAX', 21)), $plan->description, $options, $plan);
    }

    /**
     * @throws ApiException
     */
    public function create()
    {
        // ToDo: Fix mulitple interfaces.
        if (!$this->owner->ptmCustomer || !$this->owner->ptmCustomer->mandate_id) {
            throw new \Exception("Mollie customer doesn't have mandateID!");
        }
        // Create subscription entry
        $subscription = $this->buildSubscription();
        // Check if confirmation payment is required or if
        // customer is merging subscriptions.
        if ($this->forceConfirmationPayment || $this->builder->merging){

            if ($this->builder->merging){
                // Change variables to merge
                // Calculate amount to pay in difference
                $interval = $this->builder->interval;
                $normalDistance = now()->addMonth()->firstOfMonth()->diffInDays(now()->firstOfMonth());
                $currentDistance = $interval->nextDate()->firstOfMonth()->diffInDays(now());
                $this->builder->total = ($this->builder->total / $normalDistance) * $currentDistance;
                // Set description
                $this->builder->description = "Amount difference for merge. '{$this->builder->description}'";
            }

            $payment = $this->builder->create();
            $subscription->payments()->save($payment);
            // Return payment object
            return $this->builder->processorPayment;
        }
        // Or just create subscription using mandate
        return (new MollieSubscriptionBuilder($subscription, $this->owner))->execute();
    }

    public function executeOrder(Order$order)
    {
        if (!$this->owner->ptmCustomer()->where('interface', $this->builder->getInterface()::class)->whereNotNull('mandate_id')->exists()) {
            throw new \Exception("Interface customer doesn't have mandateID!");
        }
        // Create subscription entry
        $subscription = $this->buildSubscription();
        $this->webhookUrl = route('ptm_mollie.webhook.order.subscription',['order'=>$order->id,'subscriptionId'=>$subscription->id]);
        return (new MollieSubscriptionBuilder($subscription, $this->owner))->execute($this->builder->getInterface());
    }

    private function buildSubscription(){
        $subscription = $this->owner->subscriptions()->create([
            'subscribed_on' => $this->thread,
            'plan_id' => $this->builder->getPlan()->id ?? 0,
            'tax_percentage' => $this->builder->taxPercentage ?? 0,
            'interface'=>$this->getInterface()::class,
            'ends_at' => null,
            'cycle'=>$this->builder->interval->value,
            'cycle_started_at' => now(),
            'cycle_ends_at' => $this->builder->interval->nextDate()
        ]);
        $this->subscriptionID = $subscription->id;
        $this->webhookUrl = route(
            $this->builder->merging ?
                'ptm_mollie.webhook.payment.merge' :
                'ptm_mollie.webhook.payment.subscription',
            [
                'subscription_id' => $subscription->id,
                'fcp'=>($this->forceConfirmationPayment || $this->builder->merging) ? 'true' : 'false',
                'merging'=>$this->builder->merging ? 'true' : 'false'
            ]);
        return $subscription;
    }

    public function subscribedOn($identifier)
    {
        $this->thread = $identifier;
        return $this;
    }

    public function redirect(): ?string
    {
        return $this->builder->redirect();
    }

    public function setTax(int $tax)
    {
        $this->builder->setTax($tax);
        return $this;
    }

    public function setRedirectURL(string $url){
        $this->builder->setRedirectURL($url);
        return $this;
    }

    public function setOptions($options)
    {
        $this->builder->setOptions($options);
        return $this;
    }

    public function forceConfirmation(bool $enabled)
    {
        $this->forceConfirmationPayment = $enabled;
        $this->builder->setSequenceType(SequenceType::SEQUENCETYPE_FIRST);
        return $this;
    }

    public function setInterval(SubscriptionInterval $interval)
    {
        $this->interval = $interval;
        $this->builder->setInterval($interval);
        $this->builder->calculateTotal();
        return $this;
    }

    public function nextPaymentAt(Carbon $nextPaymentAt)
    {
        // TODO: Implement nextPaymentAt() method.
    }

    public function mustConfirmPayment(): bool
    {
        return $this->forceConfirmationPayment;
    }
}