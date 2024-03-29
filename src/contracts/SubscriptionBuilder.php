<?php
/*
 *  mollie_interface - SubscriptionBuilder.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

namespace PTM\MollieInterface\contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use PTM\MollieInterface\models\Plan;
use PTM\MollieInterface\models\SubscriptionInterval;

interface SubscriptionBuilder
{
    /**
     * @param Model $billable
     * @param Plan $plan
     * @param array $options
     * @return static
     */
    public static function fromPlan(Model $billable, Plan $plan, SubscriptionInterval $interval = SubscriptionInterval::MONTHLY, array $options = []);

    /**
     * Create a new subscription. Returns a redirect to checkout if necessary.
     *
     * @return mixed
     */
    public function create();

    /**
     * Override the default next payment date.
     *
     * @param \Carbon\Carbon $nextPaymentAt
     * @return $this
     */
    public function nextPaymentAt(Carbon $nextPaymentAt);

    /**
     * Specify subscription thread.
     *
     * @param $identifier
     * @return $this
     */
    public function subscribedOn($identifier);

    /**
     * @return string|null
     */
    public function redirect(): ?string;

    /**
     * @param int $tax
     * @return $this
     */
    public function setTax(int $tax);

    /**
     * The subscription cycle, every ... months.
     *
     * @param SubscriptionInterval $interval
     * @return $this
     */
    public function setInterval(SubscriptionInterval $interval);

    /**
     * Force a payment before the subscription is created.
     *
     * @param bool $enabled
     * @return mixed
     */
    public function forceConfirmation(bool $enabled);

    public function setRedirectURL(string $url);
}