<?php
/*
 *  mollie_interface - WebhookController.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\Http\Controllers\WebHooks;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\Http\Controllers\Controller;

abstract class WebhookController extends Controller
{
    /**
     * Fetch a payment from Mollie using its ID.
     * Returns null if the payment cannot be retrieved.
     *
     * @param string $id
     * @param array $parameters
     * @return \Mollie\Api\Resources\Payment|null
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMolliePaymentById(string $id, array $parameters = [])
    {
        try {
            return Mollie::api()->payments()->get($id, $parameters);
        } catch (ApiException $e) {
            if (! config('app.debug')) {
                // Prevent leaking information
                return null;
            }

            throw $e;
        }
    }

    public function getPayment($localSubscription, $payment, $offset){
        $amountChargedBack = $payment->amountChargedBack
            ? (float)$payment->amountChargedBack->value
            : 0.0;

        $amountRefunded = $payment->amountRefunded
            ? (float)$payment->amountRefunded->value
            : 0.0;
        $payment = $localSubscription->payments()->firstOrCreate([
            'mollie_payment_id' => $payment->id,
        ], [
            'mollie_payment_status' => $payment->status,
            'currency' => $payment->amount->currency,
            'amount' => (float)$payment->amount->value,
            'amount_refunded' => $amountRefunded,
            'amount_charged_back' => $amountChargedBack,
            'mollie_mandate_id' => $payment->mandateId,
            'first_payment_actions' => null,
            'paymentable_offset'=>$offset
        ]);
        if ($payment->mollie_payment_status !== $payment->status) $payment->mollie_payment_status = $payment->status;
        return $payment;
    }
}