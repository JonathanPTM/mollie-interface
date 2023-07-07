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
use Mollie\Api\Endpoints\CustomerEndpoint;
use PTM\MollieInterface\models\MollieCustomer;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\models\SubscriptionInterval;
use PTM\MollieInterface\traits\PaymentMethodString;

class UndoMerger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PaymentMethodString;
    public $customer;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(MollieCustomer $customer)
    {
        $this->customer = $customer;
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
        $mergedSubscription = $this->customer->getMergedSubscriptionId();
        $mollieCustomer = mollie()->customers()->get($this->customer->mollie_customer_id);

        $total_sum = 0;
        $added = 0;

        /**
         * @var Subscription $subscription
         */
        foreach ($billable->subscriptions as $subscription){
            if (!$subscription->mollie_subscription_id) continue;
            $total_sum += $subscription->plan->mandatedAmountIncl();
            $mollieSubscription = $mollieCustomer->getSubscription($subscription->mollie_subscription_id);
            if ($mollieSubscription->isActive()){
                $mollieSubscription->cancel();
            }
            $subscription->update([
                'is_merged'=>true
            ]);
            $added++;
        }

        if ($total_sum <= 0) {
            Log::info("Not creating a merged subscription update because total_sum is 0. ({$this->customer->billable_id})");
            return;
        }

        if (!$mergedSubscription) {
            $mergedSubscription = $this->buildMergedSubscription($total_sum, $mollieCustomer);
            $this->customer->mollie_subscriptions[] = $mergedSubscription->id;
            $this->customer->save();
        } else {
            $mergedSubscription->amount =  $total_sum;
        }
        $mergedSubscription->description = "Samengevoegde subscriptions van klant, bevat {$added} subscriptions.";
        $mergedSubscription->update();
    }
}