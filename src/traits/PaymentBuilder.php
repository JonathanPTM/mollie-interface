<?php
/*
 *  mollie_interface - PaymentBuilder.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\models\Redirect;
use PTM\MollieInterface\models\SubscriptionInterval;

trait PaymentBuilder
{
    /**
     * The billable model.
     *
     * @var Model
     */
    public $owner;

    /**
     * Overrides the Mollie Payment payload
     *
     * @var array
     */
    public $options;

    /**
     * The Mollie PaymentMethod
     *
     * @var string
     */
    public $method;

    /**
     * Total amount of the payment
     *
     * @var double
     */
    public $total;

    /**
     * The payment description.
     *
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $redirectUrl;

    /**
     * @var string
     */
    public $webhookUrl;

    /**
     * @var \Mollie\Api\Resources\Payment|null
     */
    public $processorPayment;

    /**
     * @var Payment|null
     */
    public $payment;

    /**
     * @var SequenceType
     */
    public $sequenceType;

    /**
     * @var integer|null
     */
    public $taxPercentage;

    /**
     * @var integer|null
     */
    public $subscriptionID;

    /**
     * @var SubscriptionInterval
     */
    public $interval;
    /**
     * @var String
     */
    public $mollieCustomerId;
    /**
     * @var String
     */
    public $cardToken;

    /**
     * @var bool
     */
    public $merging;

    /**
     * @var string
     */
    public $order_id;

    /**
     * @var Model|null
     */
    public $paymentable;

    /**
     * @param string $webhookUrl
     */
    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * Build the payment column values
     *
     * @return array|null
     */
    public function getPaymentPayload(): array|null
    {
        if (!$this->processorPayment) return null;
        return array_filter(array_merge(
            $this->getInterface()->paymentToDatabase($this->processorPayment),
            [
                'order_id' => $this->order_id,
                'billable_type' => $this->owner->getMorphClass(),
                'billable_id' => $this->owner->getKey(),
                'paymentable_type' => $this->paymentable?->getMorphClass(),
                'paymentable_id' => $this->paymentable?->getKey(),
                'interface' => $this->getInterface()::class
            ]
        ));
    }

    /**
     * @return \Mollie\Api\Resources\Payment|null
     */
    public function getProcessorPayment(): ?\Mollie\Api\Resources\Payment
    {
        return $this->processorPayment;
    }

    public function redirect(): ?Redirect
    {
        if ($this->processorPayment)
            return $this->getInterface()->getRedirect($this->processorPayment);
        return null;
    }
}
