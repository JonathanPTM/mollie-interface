<?php
/*
 *  mollie_interface - Plan.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

class Plan extends Model
{
    use HasFactory;

    protected $table = 'ptm_plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'amount',
        'intervals'
    ];

    protected $casts = [
        'intervals' => 'object'
    ];

    public static function cheapest()
    {
        return self::orderBy('amount')->first();
    }

    /**
     * Get the price ext. tax
     * @param SubscriptionInterval $interval
     * @return float
     */
    public function amount(SubscriptionInterval $interval = SubscriptionInterval::MONTHLY): float
    {
        return $this->mandatedAmount($interval);
    }


    /**
     * @param SubscriptionInterval $interval
     * @return float
     */
    public function mandatedAmount(SubscriptionInterval $interval = SubscriptionInterval::MONTHLY): float
    {
        $letter = $interval->getLetter();
        if (isset($this->intervals->$letter)) {
            return (double)$this->intervals->$letter;
        }
        if ($letter === 'd'){
            return (double)($this->amount * ($interval->value+1));
        }
        return (double)($this->amount * $interval->value);
    }

}
