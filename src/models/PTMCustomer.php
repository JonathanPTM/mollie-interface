<?php
/*
 *  mollie_interface - MollieCustomer.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PTMCustomer extends Model
{
    use HasFactory;

    protected $table = 'ptm_customers';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $primaryKey = 'billable_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'billable_type',
        'billable_id',
        'interface',
        'customer_id',
        'mandate_id',
        'merged',
        'subscriptions',
        'trial_ends_at',
        'extra_billing_information'
    ];

    protected $casts = [
        'subscriptions'=>'array'
    ];

    /**
     * Get the parent billable model (company or user).
     */
    public function billable()
    {
        return $this->morphTo();
    }

    public function api(): \Mollie\Api\Resources\Customer
    {
        return mollie()->customers()->get($this->mollie_customer_id);
    }

    public function getMergedSubscription($offset=0): ?\Mollie\Api\Resources\Subscription
    {
        if (count($this->mollie_subscriptions) < ($offset+1)) return null;
        $id = $this->mollie_subscriptions[$offset];
        if (!$id) return null;
        return $this->api()->getSubscription($id);
    }
}
