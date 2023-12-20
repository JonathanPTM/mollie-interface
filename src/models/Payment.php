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
use Illuminate\Support\Facades\Log;
use Mollie\Api\Resources\Payment as MolliePayment;
use Money\Currency;
use Money\Money;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM;
use PTM\MollieInterface\traits\PaymentMethodString;

class Payment extends \Illuminate\Database\Eloquent\Model
{
    use PaymentMethodString;

    protected $table = 'ptm_payments';
    /**
     * @var string[]
     */
    protected $casts = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'interface',
        'interface_id',
        'payment_status',
        'mandate_id',
        'method',
        'currency',
        'amount',
        'amount_refunded',
        'amount_charged_back',
        'paymentable_offset',
        'notified_at',
        'billable_type',
        'billable_id',
        'paymentable_type',
        'paymentable_id'
    ];

    /**
     * @param $payment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $actions
     * @param array $overrides
     * @return static
     */
    public static function makeOrFindFromPayment($payment, Model $owner, Model $billable, PaymentProcessor $interface = null, array $overrides = [], int $offset = null): self
    {
        if (!$interface) $interface = PTM::getInterface();
        $found = $owner->payments()->firstWhere('interface_id', $payment->id);
        if ($found) {

            $found->fill(array_merge(
                $interface->makePaymentFromProvider($payment),
                [
                    'interface'=>$interface::class,
                    'paymentable_offset'=>$offset
                ],
                $overrides
            ));
            return $found;
        }
        return self::makeFromPayment($payment, $owner, $billable, $interface, $overrides, $offset);
    }

    /**
     * @param $payment
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param array $actions
     * @param array $overrides
     * @return static
     */
    public static function makeFromPayment($payment, Model $owner, Model $billable, PaymentProcessor $interface = null, array $overrides = [], int $offset = null): self
    {
        if (!$interface) $interface = PTM::getInterface();

        Log::debug("Payment billable id is {$billable->id}", [$billable->getMorphClass()]);

        return $owner->payments()
            ->make(
                array_merge(
                    $interface->makePaymentFromProvider($payment),
                    [
                        'interface'=>$interface::class,
                        'paymentable_offset'=>$offset
                    ],
                    $overrides
                ))
            ->billable()
            ->associate($billable);

    }

    /**
     * Retrieve an Order by the Mollie Payment id.
     *
     * @param $id
     * @return static
     */
    public static function findByPaymentId($id, PaymentProcessor $interface = null): ?self
    {
        if (!$interface) $interface = PTM::getInterface();
        return static::where('interface_id', $id)
            ->where('interface', $interface::class)
            ->first();
    }

    /**
     * @return PaymentProcessor
     */
    public function getInterface(){
        if (!$this->interface) return PTM::getInterface();
        return PTM::importInterface($this->interface);
    }

    public function getInterfacePayment(){
        return $this->getInterface()->getPayment($this);
    }

    /**
     * Retrieve a Payment by the Mollie Payment id or throw an Exception if not found.
     *
     * @param $id
     * @return static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByPaymentIdOrFail($id, PaymentProcessor $interface = null): self
    {
        if (!$interface) $interface = PTM::getInterface();
        return static::where('interface_id', $id)->where('interface', $interface::class)->firstOrFail();
    }

    /**
     * Get the parent paymentable model.
     */
    public function paymentable()
    {
        return $this->morphTo('paymentable');
    }

    /**
     * Get the parent paymentable model.
     */
    public function billable()
    {
        return $this->morphTo('billable');
    }

    public function markAsPaid(){
        $this->update([
            'payment_status' => 'paid'
        ]);
    }

    /**
     * @return bool
     */
    public function isPaid(){
        return $this->payment_status === 'paid';
    }

    public function getStatusAttribute()
    {
        return $this->payment_status;
    }

}