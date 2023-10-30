<?php
/*
 *  mollie_interface - PaymentController.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use PTM\MollieInterface\Events\FirstPaymentFailed;
use PTM\MollieInterface\Events\FirstPaymentPaid;
use PTM\MollieInterface\Events\PaymentPaid;
use PTM\MollieInterface\models\Subscription;
use PTM\MollieInterface\Repositories\Handlers\FirstPaymentHandler;
use PTM\MollieInterface\Repositories\Handlers\PaymentHandler;
use PTM\MollieInterface\Repositories\Handlers\PaymentMethodHandler;

class PaymentMethodController extends WebhookController
{
    public function hooked(Request $request){
        $payment = $this->getMolliePaymentById($request->get('id'));
        if (!$payment){
            return new Response(null, 404);
        }
        $order = (new PaymentHandler($payment))->execute();
        if ($payment->isPaid()){

            Event::dispatch(new PaymentPaid($payment, $order));
            $payment->webhookUrl = route('ptm_mollie.webhook.payment.after');
            $payment->update();

            $subscription = Subscription::find($request->route('id'));
            if (!$subscription) return new Response(null, 402);

            (new PaymentMethodHandler($payment, $subscription))->execute();

        } else {
            Event::dispatch(new FirstPaymentFailed($payment));
        }
        return new Response(null, 200);
    }
}