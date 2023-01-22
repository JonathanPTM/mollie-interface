<?php
/*
 *  mollie_interface - Subscription.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Carbon\Carbon;

class Subscription extends \Illuminate\Database\Eloquent\Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'ptm_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscribed_on',
        'plan_id',
        'tax_percentage',
        'ends_at',
        'cycle_started_at',
        'cycle_ends_at'
    ];

    /**
     * Get the subscription's payments.
     */
    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    /**
     * Get current plan.
     */
    public function plan()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    /**
     * Get billable.
     */
    public function billable()
    {
        return $this->morphTo('billable');
    }

    /**
     * Get current cycle.
     * @return SubscriptionInterval
     */
    public function getCycle()
    {
        if (!$this->cycle_started_at || !$this->cycle_ends_at) return SubscriptionInterval::MONTHLY;
        try {
            return SubscriptionInterval::from(Carbon::parse($this->cycle_started_at)->floorMonth()->diffInMonths(Carbon::parse($this->cycle_ends_at)->floorMonth()));
        } catch (Exception$exception) {
            \Illuminate\Support\Facades\Log::error($exception);
            return SubscriptionInterval::MONTHLY;
        }
    }
}