<?php
/*
 *  mollie_interface - MollieSubscriptionBuilder.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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
use Illuminate\Support\Facades\Event;
use Mollie\Api\Resources\Payment;
use PTM\MollieInterface\contracts\Handler;
use PTM\MollieInterface\Events\SubscriptionCreated;
use PTM\MollieInterface\jobs\MergeSubscriptions;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\traits\PTMBillable;

class MollieSubscriptionBuilder implements Handler
{
    /** @var PTMBillable|Model */
    protected $owner;

    /** @var Subscription */
    protected $subscription;
    protected $hasFirstPayment;
    public function __construct(Subscription $subscription, Model $owner, bool $hasFirstPayment=true)
    {
        $this->subscription = $subscription;
        $this->owner = $owner;
        $this->hasFirstPayment = $hasFirstPayment;
    }
    public function execute()
    {
        $mollieSubscription = null;
        $isMerged = $this->owner->isMerged();
        if (!$isMerged){
            $mollieSubscription = $this->owner->CustomerAPI()->createSubscription($this->subscription->toMollie(!$this->hasFirstPayment));
            $this->subscription->update([
                'mollie_subscription_id'=>$mollieSubscription->id
            ]);
        } else {
            // Run Merge job!
            MergeSubscriptions::dispatch($this->owner->mollieCustomer)
                ->onQueue(config('ptm_subscription.bus'));
        }
        Event::dispatch(new SubscriptionCreated($this->subscription, $isMerged));
        return $mollieSubscription;
    }
}