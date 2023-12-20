<?php
/*
 *  mollie_interface - Subscription.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\models;

use Carbon\Carbon;
use Exception;
use PTM;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\Builders\Builder;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\Events\SubscriptionCancelled;
use PTM\MollieInterface\Events\SubscriptionChange;
use PTM\MollieInterface\jobs\MergeSubscriptions;
use PTM\MollieInterface\Builders\SimplePayment;
use PTM\MollieInterface\traits\PaymentMethodString;

class Subscription extends \Illuminate\Database\Eloquent\Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, PaymentMethodString;

    protected $table = 'ptm_subscriptions';

    private Builder $processor;

    public function __construct()
    {
        parent::__construct();
        $this->processor = new Builder();
        if ($this->interface !== null){
            $this->processor->setInterface($this->interface);
        }
    }

    /**
     * @return \PTM\MollieInterface\contracts\PaymentProcessor
     */
    public function getInterface(){
        return $this->processor->getInterface();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscribed_on',
        'plan_id',
        'interface',
        'interface_id',
        'mandate_id',
        'tax_percentage',
        'is_merged',
        'ends_at',
        'cycle',
        'cycle_started_at',
        'cycle_ends_at'
    ];

    /**
     * Get the subscription's payments.
     */
    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    /**
     * Get current plan.
     */
    public function plan()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    /**
     * Get billable.
     */
    public function billable()
    {
        return $this->morphTo('billable');
    }

    /**
     * Retrieve an Order by the Mollie Payment id.
     *
     * @param $id
     * @return Subscription|null
     */
    public static function findBySubscriptionId($id, PaymentProcessor$interface=null): ?self
    {
        if (!$interface) $interface = PTM::getInterface();
        return static::where('interface_id', $id)->where('interface', $interface::class)->first();
    }

    /**
     * Retrieve an Order by the Mollie Payment id.
     *
     * @param $id
     * @return Subscription|null
     */
    public static function findByPaymentId($id): ?self
    {
        return self::findBySubscriptionId($id);
    }

    /**
     * Get current interval.
     * @return SubscriptionInterval
     */
    public function getCycle(){
        return $this->getInterval();
    }

    public function updateCycle($last_payment=null, $next_payment=null){
        if (!$last_payment && !$next_payment) throw new Exception("Last payment or next payment has to be given!");
        if (!$last_payment){
            $last_payment = $this->getCycle()->previousDate($next_payment);
        }
        if (!$next_payment){
            $this->update([
                'cycle_started_at'=>$last_payment,
                'cycle_ends_at'=>$this->getCycle()->nextDate($last_payment)
            ]);
            return;
        }
        $this->update([
            'cycle_started_at'=>$last_payment,
            'cycle_ends_at'=>$next_payment
        ]);
    }
    public function getInterval()
    {
        if (!$this->cycle_started_at || !$this->cycle_ends_at) return SubscriptionInterval::MONTHLY;
        if ($this->cycle) return SubscriptionInterval::from($this->cycle);
        try {
            $interval = $this->getInterface()->getCycle($this);
            $this->update([
                'cycle'=>$interval->value
            ]);
        } catch (Exception$exception) {
            \Illuminate\Support\Facades\Log::error($exception);
            return SubscriptionInterval::MONTHLY;
        }
        return $interval ?? SubscriptionInterval::MONTHLY;
    }

    public function endSubscription(bool $force=false){
        if (!$this->interface_id) {
            throw new \RuntimeException("Subscription hasn't started yet. Mollie subscription ID is missing.");
        }
        // End subscription at mollie's end...
        if (!$this->is_merged) $this->getInterface()->cancelSubscription($this);

        $this->update([
            'ends_at'=>$force ? now() : $this->cycle_ends_at
        ]);
        Event::dispatch(new SubscriptionCancelled($this));
        if ($this->is_merged){
            MergeSubscriptions::dispatch($this->billable->ptmCustomer()->firstWhere('interface', $this->getInterface()::class))
                ->onQueue(config('ptm_subscription.bus'));
        }
        return $this;
    }

    public function changeInterval(SubscriptionInterval $interval, $resetStartCycle=false){
        if ($this->mollie_subscription_id){
            $mollieSubscription = $this->billable->CustomerAPI()->getSubscription($this->mollie_subscription_id);
            $mollieSubscription->interval = $interval->toMollie();
            if ($resetStartCycle){
                $mollieSubscription->startDate = now()->format('Y-m-d');
            }
            $mollieSubscription->update();
        }
        $this->update([
            'cycle' => $interval->value,
            'cycle_started_at' => $resetStartCycle ? now() : $this->cycle_started_at,
            'cycle_ends_at' => $interval->nextDate($resetStartCycle ? now() : $this->cycle_started_at)
        ]);
        return $this;
    }

    public function changePlan(Plan$plan){
        if ($this->mollie_subscription_id && !$this->is_merged){
            $mollieSubscription = $this->billable->CustomerAPI()->getSubscription($this->mollie_subscription_id);
            $mollieSubscription->amount = $this->money_to_mollie_array($plan->mandatedAmountIncl($this->getInterval(), $this->tax_percentage));
            $mollieSubscription->description = $this->getDiscriminator()." - ".$this->plan->description;
            $mollieSubscription->update();
        }
        $this->plan_id = $plan->id;
        $this->save();
        Event::dispatch(new SubscriptionChange($this));
        if ($this->is_merged){
            MergeSubscriptions::dispatch($this->billable->mollieCustomer)
                ->onQueue(config('ptm_subscription.bus'));
        }
        return $this;
    }

    /**
     * @param $method
     * @param $cardToken
     * @param $cost
     * @param $options
     * @return Order|Redirect
     * @throws Exception
     */
    public function changePaymentMethod($method=null, $cardToken=null, $cost=0.25, $options=[]): Order|Redirect
    {
        $builder = Order::Builder();
        $builder->setBillable($this->billable);
        $payment = new SimplePayment($this->billable, $cost, "Change payment method", $options);

        if ($method) $payment->setMethod($method);
        if ($cardToken) $payment->setCardToken($cardToken);

        $payment->setSequenceType(SequenceType::SEQUENCETYPE_FIRST);

        $builder->setPayment($payment);
        $subscriptionID = $this->id;
        $builder->setPaymentSaver(function ($p) use ($subscriptionID) {
            Subscription::find($subscriptionID)->payments()->save($p);
        });

        $builder->addAction(new PTM\MollieInterface\jobs\changePaymentMethod($this));

        return $builder->build();
    }

    public function isActive(){
        if ($this->ends_at && Carbon::parse($this->ends_at)->isPast()) return false;
        return $this->payments()->where('payment_status', 'paid')->exists();
    }

    public function mandatePayment(){
        return $this->hasOne(Payment::class, 'mandate_id', 'mandate_id');
    }

    /**
     * get periodic expenses
     * @return mixed
     */
    public function getAmount(){
        return $this->plan->mandatedAmountIncl($this->getInterval(), $this->tax_percentage);
    }

    public function getDiscriminator(){
        if ($this->subscribed_on) {
            $arr = explode('-', $this->subscribed_on);
            return $arr[0];
        }
        return $this->id;
    }

}