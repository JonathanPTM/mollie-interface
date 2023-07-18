<?php
/*
 *  mollie_interface - MergeSubscriptions.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Imtigger\LaravelJobStatus\Trackable;
use Mollie\Api\Endpoints\CustomerEndpoint;
use PTM\MollieInterface\models\MollieCustomer;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\traits\PaymentMethodString;

class MergeSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PaymentMethodString, Trackable;
    public $customer;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(MollieCustomer $customer)
    {
        $this->customer = $customer;
        $this->setInput(['billable_id'=>$customer->billable_id]);
    }

    private function buildMergedSubscription($total,$mollieCustomer):\Mollie\Api\Resources\Subscription{
        $customer = $this->customer;
        $interval = SubscriptionInterval::MONTHLY;
        $date = Carbon::today();
        if (!Carbon::today()->firstOfMonth()->isSameDay($date)){
            $date = $date->addMonth()->firstOfMonth();
        }
        return $mollieCustomer->createSubscription([
            'amount'=>$this->money_to_mollie_array($total),
            'interval'=>$interval->toMollie(),
            'startDate'=> $date->format('Y-m-d'),
            'description'=>"Samengevoegde subscriptions van klant.",
            'mandateId'=>$customer->mollie_mandate_id,
            'webhookUrl'=>route('ptm_mollie.webhook.payment.subscription', ['merged' => $customer->billable_id]),
            'metadata'=>[
                'merged_on'=>Carbon::now()->format("d-m-Y H:i:s"),
                'billable'=>[
                    'type'=>$customer->billable_type,
                    'id'=>$customer->billable_id
                ]
            ]
        ]);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $billable = $this->customer->billable;

        /**
         * @var ?\Mollie\Api\Resources\Subscription $mergedSubscription
         */
        $mergedSubscription = $this->customer->getMergedSubscription();
        $mollieCustomer = mollie()->customers()->get($this->customer->mollie_customer_id);

        $total_sum = 0;
        $added = 0;

        DB::beginTransaction();

        /**
         * @var Subscription $subscription
         */
        foreach ($billable->subscriptions as $subscription){
            if (!$subscription->mollie_subscription_id) continue;
            $total_sum += $subscription->plan->mandatedAmountIncl();
            $mollieSubscription = false;
            try {
                $mollieSubscription = $mollieCustomer->getSubscription($subscription->mollie_subscription_id);
            } catch (\Exception$exception){
                Log::error($exception);
            }
            if (!$mollieSubscription) {
                Log::info("Subscription was no longer found on mollie ($subscription->mollie_subscription_id)");
                continue;
            }
            if ($mollieSubscription->isActive()){
                $mollieSubscription->cancel();
            }
            $subscription->update([
                'is_merged'=>true
            ]);
            $added++;
        }

        DB::commit();

        if ($total_sum <= 0) {
            Log::info("Not creating a merged subscription update because total_sum is 0. ({$this->customer->billable_id})");
            return;
        }

        if (!$mergedSubscription) {
            $ids = [];
            $mergedSubscription = $this->buildMergedSubscription($total_sum, $mollieCustomer);
            $ids[] = $mergedSubscription->id;
            $this->customer->mollie_subscriptions = $ids;
            $this->customer->save();
        } else {
            $mergedSubscription->amount =  $total_sum;
        }
        $mergedSubscription->description = "Samengevoegde subscriptions van klant, bevat {$added} subscriptions.";
        $this->customer->update([
            'merge_subscriptions'=>true
        ]);
        $mergedSubscription->update();
    }
}