<?php
/*
 *  mollie_interface - isMollieCustomer.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Mollie\Laravel\Facades\Mollie;
use PTM\MollieInterface\contracts\PaymentProcessor;
use PTM\MollieInterface\models\PTMCustomer;
use PTM\MollieInterface\PTMFacade;

trait isMollieCustomer
{
    /**
     * @return bool
     */
    public function needsFirstPayment(){
        return !$this->ptmCustomer()->whereNotNull('mandate_id')->exists();
    }
    /**
     * Get the mollie customer.
     */
    public function ptmCustomer()
    {
        return $this->morphMany(PTMCustomer::class, 'billable');
    }

    /**
     * Retrieve the Mollie customer ID for this model
     *
     * @return string
     */
    public function CustomerId(PaymentProcessor $interface=null)
    {
        if (!$interface) $interface = PTMFacade::getInterface();
        if (!$this->ptmCustomer()->where('interface',$interface::class)->exists()) {
            return $this->createAsCustomer()->id;
        }

        return $this->ptmCustomer()->where('interface',$interface::class)->first()->customer_id;
    }

    /**
     * Create a Mollie customer for the billable model.
     *
     * @param array $override_options
     * @return \Mollie\Api\Resources\Customer
     */
    public function createAsCustomer(array $override_options = [], PaymentProcessor$interface=null): \Mollie\Api\Resources\Customer
    {
        if (!$interface) $interface = PTMFacade::getInterface();

        $customer = $interface->createCustomer($this, $override_options);

        $this->ptmCustomer()->updateOrCreate([
            'interface'=>$interface::class,
            'customer_id' => $customer->id
        ]);

        return $customer;
    }

    public function mandateId(PaymentProcessor$interface=null)
    {
        if (!$interface) $interface = PTMFacade::getInterface();
        return $this->ptmCustomer()->where('interface',$interface::class)->mandate_id;
    }

    /**
     * @return \Mollie\Api\Resources\Customer
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function CustomerAPI(PaymentProcessor$interface=null)
    {
        if (!$interface) $interface = PTMFacade::getInterface();
        return $interface->CustomerAPI($this->CustomerId($interface));
    }
}