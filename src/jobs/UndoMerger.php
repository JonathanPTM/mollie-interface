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
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
use PTM\MollieInterface\Repositories\SubscriptionBuilder;
use PTM\MollieInterface\traits\PaymentMethodString;

class UndoMerger implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PaymentMethodString, Trackable;
    public $customer;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->customer->billable_id;
    }

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

    private function recreateSubscription($subscription, $mergedSubscription, $mollieCustomer){
        // Recreate subscription
        $next = Carbon::parse($mergedSubscription->nextPaymentDate);
        Log::debug("Recreating subscription ($subscription->id)", [$next]);
        $subscription->updateCycle(null, $next);
        $new_instance = $mollieCustomer->createSubscription($subscription->toMollie());
        $subscription->update([
            'mollie_subscription_id'=>$new_instance->id,
            'is_merged' => false
        ]);
    }

    private function excecutor($billable){
        /**
         * @var ?\Mollie\Api\Resources\Subscription $mergedSubscription
         */
        $mergedSubscription = $this->customer->getMergedSubscription();
        if (!$mergedSubscription) return ['status'=>false,'message'=>'No merged subscription was found.','total'=>0];
        $mollieCustomer = mollie()->customers()->get($this->customer->mollie_customer_id);
        if (!$mollieCustomer) return ['status'=>false,'message'=>'No payment customer was found.','total'=>0];

        if (!$mergedSubscription->isActive()) return ['status'=>false,'message'=>'No active merged subscription was found.','total'=>0];

        $dismembered = 0;
        $grouped = 0;
        $offset = 0;

        /**
         * @var Subscription $subscription
         */
        foreach ($billable->subscriptions as $subscription){
            if (!$subscription->is_merged) continue;

            // Check if grouped is greater than config('ptm_subscription.break')
            // In case change merged subscription...
            if ($grouped >= config('ptm_subscription.break')){
                $offset++;
                $nextSub = $this->customer->getMergedSubscription($offset);
                if ($nextSub) {
                    $mergedSubscription = $nextSub;
                } else {
                    $offset = 0;
                    $mergedSubscription = $this->customer->getMergedSubscription();
                }
                $grouped = 0;
                Log::debug("Switched merged subscription to next in line ($offset)");
            }

            if (!$subscription->mollie_subscription_id) {
                $this->recreateSubscription($subscription, $mergedSubscription, $mollieCustomer);
            } else {
                // Check if existing mollie subscription isn't active...
                $mollieSubscription = $mollieCustomer->getSubscription($subscription->mollie_subscription_id);
                if (!$mollieSubscription->isActive()){
                    $this->recreateSubscription($subscription, $mergedSubscription, $mollieCustomer);
                }
            }
            $dismembered++;
            $grouped = $grouped + $subscription->getAmount();
        }

        if ($dismembered <= 0) {
            Log::info("No subscriptions were unmerged. ({$this->customer->billable_id})");
            return ['status'=>false,'message'=>'No subscriptions were found to be unmerged','total'=>$dismembered];
        }

        // Cancel merged subscriptions
        foreach ($this->customer->mollie_subscriptions as $subId){
            $sub = mollie()->customers()->get($this->customer->mollie_customer_id)->getSubscription($subId);
            if ($sub->isActive()) $sub->cancel();
        }

        // Change database
        $this->customer->update([
            'mollie_subscriptions'=>[],
            'merge_subscriptions'=>false
        ]);

        return ['status'=>true,'message'=>null,'total'=>$dismembered];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $billable = $this->customer->billable;
        $result = $this->excecutor($billable);
        Log::debug(json_encode($result));
        $this->setOutput($result);


    }
}