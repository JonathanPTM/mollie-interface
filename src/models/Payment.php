<?php
/*
 *  mollie_interface - Payment.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Resources\Payment as MolliePayment;
use Money\Currency;
use Money\Money;
use PTM\MollieInterface\traits\PaymentMethodString;

class Payment extends \Illuminate\Database\Eloquent\Model
{
    use PaymentMethodString;

    protected $table = 'ptm_payments';
    /**
     * @var string[]
     */
    protected $casts = [
        'first_payment_actions' => 'object',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'mollie_payment_id',
        'mollie_payment_status',
        'mollie_mandate_id',
        'currency',
        'amount',
        'amount_refunded',
        'amount_charged_back',
        'notified_at'
    ];

    /**
     * @param MolliePayment $payment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $actions
     * @param array $overrides
     * @return static
     */
    public static function makeFromMolliePayment(MolliePayment $payment, Model $owner, array $actions = [], array $overrides = []): self
    {
        $amountChargedBack = $payment->amountChargedBack
            ? self::mollie_object_to_money($payment->amountChargedBack)
            : new Money(0, new Currency($payment->amount->currency));

        $amountRefunded = $payment->amountRefunded
            ? self::mollie_object_to_money($payment->amountRefunded)
            : new Money(0, new Currency($payment->amount->currency));

        $localActions = !empty($actions) ? $actions : $payment->metadata->actions ?? null;

        return static::make(array_merge([
            'mollie_payment_id' => $payment->id,
            'mollie_payment_status' => $payment->status,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'currency' => $payment->amount->currency,
            'amount' => (float)self::mollie_object_to_money($payment->amount)->getAmount(),
            'amount_refunded' => (float)$amountRefunded->getAmount(),
            'amount_charged_back' => (float)$amountChargedBack->getAmount(),
            'mollie_mandate_id' => $payment->mandateId,
            'first_payment_actions' => $localActions,
        ], $overrides));
    }

    /**
     * Retrieve an Order by the Mollie Payment id.
     *
     * @param $id
     * @return static
     */
    public static function findByPaymentId($id): ?self
    {
        return static::where('mollie_payment_id', $id)->first();
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
        if ($id instanceof \Mollie\Api\Resources\Payment) $id = $id->id;
        return static::where('mollie_payment_id', $id)->firstOrFail();
    }

    /**
     * Get the parent paymentable model.
     */
    public function paymentable()
    {
        return $this->morphTo();
    }

    /**
     * Find a Payment by the Mollie payment id, or create a new Payment record from a Mollie payment if not found.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $actions
     * @return static
     */
    public static function findByMolliePaymentOrCreate(MolliePayment $molliePayment, Model $owner): self
    {
        $payment = self::findByPaymentId($molliePayment->id);

        if ($payment) {
            return $payment;
        }

        return $owner->payments()->create(array_filter([
            'mollie_payment_id' => $molliePayment->id,
            'mollie_payment_status' => $molliePayment->status,
            'mollie_mandate_id' => $molliePayment->mandateId,
            'currency' => $molliePayment->amount->currency,
            'amount' => $molliePayment->amount->value,
            'amount_refunded' => ($molliePayment->amountRefunded ? $molliePayment->amountRefunded->value : null),
            'amount_charged_back' => ($molliePayment->amountChargedBack ? $molliePayment->amountChargedBack->value : null)
        ]));
    }
}