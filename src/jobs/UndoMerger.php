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
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600;

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->customer->billable->id;
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(MollieCustomer $customer)
    {
        $this->customer = $customer;
        $this->setInput($customer->billable_id);
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

        /**
         * @var Subscription $subscription
         */
        foreach ($billable->subscriptions as $subscription){
            if (!$subscription->mollie_subscription_id) continue;
            if (!$subscription->is_merged) continue;

            $mollieSubscription = $mollieCustomer->getSubscription($subscription->mollie_subscription_id);
            if (!$mollieSubscription->isActive()){
                // Recreate subscription
                $next = Carbon::parse($mergedSubscription->nextPaymentDate);
                $subscription->updateCycle(null, $next);
                $new_instance =  $mollieCustomer->createSubscription($subscription->toMollie());
                $subscription->update([
                    'mollie_subscription_id'=>$new_instance->id
                ]);
            }
            $subscription->is_merged = false;
            $subscription->save();
            $dismembered++;
        }

        if ($dismembered <= 0) {
            Log::info("No subscriptions were unmerged. ({$this->customer->billable_id})");
            return ['status'=>false,'message'=>'No subscriptions were found to be unmerged','total'=>$dismembered];
        }

        if ($mergedSubscription->isActive()) $mergedSubscription->cancel();
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
        DB::beginTransaction();
        $result = $this->excecutor($billable);
        DB::commit();
        $this->setOutput($result);


    }
}