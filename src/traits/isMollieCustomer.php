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
use PTM\MollieInterface\models\MollieCustomer;

trait isMollieCustomer
{
    /**
     * @return bool
     */
    public function needsFirstPayment(){
        return !(!empty($this->mollieCustomer) && !empty($this->mollieCustomer->mollie_mandate_id));
    }
    /**
     * Get the mollie customer.
     */
    public function mollieCustomer()
    {
        return $this->morphOne(MollieCustomer::class, 'billable');
    }

    /**
     * Retrieve the Mollie customer ID for this model
     *
     * @return string
     */
    public function mollieCustomerId()
    {
        if (!$this->mollieCustomer()->exists()) {
            return $this->createAsMollieCustomer()->id;
        }

        return $this->mollieCustomer->mollie_customer_id;
    }

    /**
     * Create a Mollie customer for the billable model.
     *
     * @param array $override_options
     * @return \Mollie\Api\Resources\Customer
     */
    public function createAsMollieCustomer(array $override_options = []): \Mollie\Api\Resources\Customer
    {
        $options = array_merge($this->mollieCustomerFields(), $override_options);

        $customer = Mollie::api()->customers()->create($options);

        $this->mollieCustomer()->updateOrCreate([
            'mollie_customer_id' => $customer->id
        ]);

        return $customer;
    }

    public function mollieCustomerFields()
    {
        return [
            'name'=> $this->name,
            'email' => $this->email
        ];
    }

    public function mollieMandateId()
    {
        return $this->mollieCustomer->mollie_mandate_id;
    }

    /**
     * @return \Mollie\Api\Resources\Customer
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function CustomerAPI()
    {
        return Mollie::api()->customers()->get($this->mollieCustomerId());
    }
}