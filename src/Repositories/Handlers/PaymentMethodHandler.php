<?php
/*
 *  mollie_interface - PaymentHandler.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\Repositories\Handlers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Resources\Payment;
use PTM\MollieInterface\contracts\Handler;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\traits\PTMBillable;

class PaymentMethodHandler implements Handler
{
    /** @var PTMBillable */
    protected $owner;

    /** @var Payment */
    protected $molliePayment;
    /** @var Subscription */
    protected $subscription;
    /**
     * FirstPaymentHandler constructor.
     *
     * @param Payment $molliePayment
     */
    public function __construct(Payment $molliePayment, Subscription$subscription)
    {
        $this->molliePayment = $molliePayment;
        $this->subscription = $subscription;
        $this->owner = $this->extractOwner();
    }
    public function execute(PaymentProcessor $interface)
    {
        $user = $this->subscription->billable;
        try {
            $this->subscription->update([
                'mandate_id'=>$this->molliePayment->mandateId
            ]);
            $newMollieSubscription = $interface->createSubscription($user, $this->subscription);
        } catch (\Exception $exception){
            Log::error($exception);
            return false;
        }
        // Destroy old subscription...
        $interface->cancelSubscription($this->subscription);
        return true;
    }

    /**
     * Fetch the owner model using the mandate payment metadata.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function extractOwner()
    {
        $ownerType = $this->molliePayment->metadata->owner->type;
        $ownerID = $this->molliePayment->metadata->owner->id;

        $ownerClass = Relation::getMorphedModel($ownerType) ?? $ownerType;

        return $ownerClass::findOrFail($ownerID);
    }

    /**
     * Retrieve the owner object.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getOwner()
    {
        return $this->owner;
    }
}