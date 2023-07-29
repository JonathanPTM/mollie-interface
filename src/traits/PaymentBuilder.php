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
use PTM\MollieInterface\models\SubscriptionInterval;

trait PaymentBuilder
{
    /**
     * The billable model.
     *
     * @var Model
     */
    protected $owner;

    /**
     * Overrides the Mollie Payment payload
     *
     * @var array
     */
    protected $options;

    /**
     * The Mollie PaymentMethod
     *
     * @var array
     */
    protected $method;

    /**
     * Total amount of the payment
     *
     * @var double
     */
    protected $total;

    /**
     * The payment description.
     *
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $redirectUrl;

    /**
     * @var string
     */
    protected $webhookUrl;

    /**
     * @var \Mollie\Api\Resources\Payment|null
     */
    protected $molliePayment;

    /**
     * @var Payment|null
     */
    protected $payment;

    /**
     * @var SequenceType
     */
    protected $sequenceType;

    /**
     * @var integer|null
     */
    protected $taxPercentage;

    /**
     * @var integer|null
     */
    protected $subscriptionID;

    /**
     * @var SubscriptionInterval
     */
    protected $interval;
    /**
     * @var String
     */
    protected $mollieCustomerId;

    /**
     * Create a Mollie Amount array from a Money object.
     *
     * @param float
     * @return array $array
     */
    private function money_to_mollie_array(float $money)
    {
        return [
            'currency' => "EUR",
            'value' => number_format($money, 2, '.')
        ];
    }

    /**
     * Build the Mollie Payment Payload
     *
     * @return array
     */
    public function getMolliePayload(): array
    {
        Log::debug("Mollie payload of: $this->total.");
        return array_filter(array_merge([
            'sequenceType' => $this->sequenceType,
            'method' => $this->method,
            'customerId' => $this->mollieCustomerId ?? $this->owner->mollieCustomerId(),
            'description' => $this->description,
            'amount' => $this->money_to_mollie_array($this->total),
            'webhookUrl' => $this->webhookUrl,
            'redirectUrl' => $this->redirectUrl,
            'metadata' => [
                'owner' => [
                    'type' => $this->owner->getMorphClass(),
                    'id' => $this->owner->getKey(),
                ],
                'subscription' => $this->subscriptionID
            ],
        ], $this->options));
    }

    /**
     * Build the payment column values
     *
     * @return null
     */
    public function getPaymentPayload()
    {
        if (!$this->molliePayment) return null;
        return array_filter([
            'mollie_payment_id' => $this->molliePayment->id,
            'mollie_payment_status' => $this->molliePayment->status,
            'mollie_mandate_id' => $this->molliePayment->mandateId,
            'currency' => $this->molliePayment->amount->currency,
            'amount' => $this->molliePayment->amount->value,
            'amount_refunded' => ($this->molliePayment->amountRefunded ? $this->molliePayment->amountRefunded->value : null),
            'amount_charged_back' => ($this->molliePayment->amountChargedBack ? $this->molliePayment->amountChargedBack->value : null)
        ]);
    }
}
