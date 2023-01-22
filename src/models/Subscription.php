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
use Illuminate\Support\Facades\Event;
use PTM\MollieInterface\Events\SubscriptionCancelled;
use PTM\MollieInterface\Events\SubscriptionChange;
use PTM\MollieInterface\traits\PaymentMethodString;

class Subscription extends \Illuminate\Database\Eloquent\Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, PaymentMethodString;

    protected $table = 'ptm_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscribed_on',
        'plan_id',
        'mollie_subscription_id',
        'tax_percentage',
        'ends_at',
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
     * @return static
     */
    public static function findByPaymentId($id): ?self
    {
        return static::where('mollie_subscription_id', $id)->first();
    }

    /**
     * Retrieve a Payment by the Mollie Payment id or throw an Exception if not found.
     *
     * @param $id
     * @return static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByPaymentIdOrFail($id): self
    {
        if ($id instanceof \Mollie\Api\Resources\Subscription) $id = $id->id;
        return static::where('mollie_subscription_id', $id)->firstOrFail();
    }

    /**
     * Get current interval.
     * @return SubscriptionInterval
     */
    public function getCycle(){
        return $this->getInterval();
    }
    public function getInterval()
    {
        if (!$this->cycle_started_at || !$this->cycle_ends_at) return SubscriptionInterval::MONTHLY;
        try {
            return SubscriptionInterval::from(Carbon::parse($this->cycle_started_at)->floorMonth()->diffInMonths(Carbon::parse($this->cycle_ends_at)->floorMonth()));
        } catch (Exception$exception) {
            \Illuminate\Support\Facades\Log::error($exception);
            return SubscriptionInterval::MONTHLY;
        }
    }

    public function endSubscription(bool $force=false){
        if (!$this->mollie_subscription_id) {
            throw new \RuntimeException("Subscription hasn't started yet. Mollie subscription ID is missing.");
        }
        // End subscription at mollie's end...
        $billable = $this->billable;
        $billable->CustomerAPI()->getSubscription($this->mollie_subscription_id)->cancel();

        $this->update([
            'ends_at'=>$force ? now() : $this->cycle_ends_at
        ]);
        Event::dispatch(new SubscriptionCancelled($this));
    }

    public function changePlan(Plan$plan){
        if ($this->mollie_subscription_id){
            $mollieSubscription = $this->billable->CustomerAPI()->getSubscription($this->mollie_subscription_id);
            $mollieSubscription->amount = $this->money_to_mollie_array($plan->mandatedAmountIncl($this->getInterval(), $this->tax_percentage));
            $mollieSubscription->update();
        }
        $this->plan_id = $plan->id;
        $this->save();
        Event::dispatch(new SubscriptionChange($this));
    }

    public function toMollie($startNow=false){
        $interval = $this->getInterval();
        return [
            'amount'=>$this->money_to_mollie_array($this->plan->mandatedAmountIncl($interval, $this->tax_percentage)),
            'interval'=>$interval->toMollie(),
            'startDate'=> ($startNow ? now() : Carbon::parse($this->cycle_ends_at))->format('Y-m-d'),
            'description'=>($this->subscribed_on ?? $this->id)." - ".$this->plan->description,
            'mandateId'=>$this->billable->mollieCustomer->mollie_mandate_id,
            'webhookUrl'=>route('ptm_mollie.webhook.payment.subscription', ['subscription_id' => $this->id]),
            'metadata'=>[
                'subscribed_on'=>$this->subscribed_on,
                'billable'=>[
                    'type'=>$this->billable_type,
                    'id'=>$this->billable_id
                ]
            ]
        ];
    }
}