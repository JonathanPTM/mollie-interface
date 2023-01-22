<?php
/*
 *  mollie_interface - SubscriptionInterval.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

enum SubscriptionInterval: int
{
    case DAILY = 0;
    case MONTHLY = 1;
    case QUARTERLY = 4;
    case SEMIYEARLY = 6;
    case YEARLY = 12;

    public function getName(): string
    {
        return match ($this) {
            self::DAILY => 'Dagelijks',
            self::MONTHLY => 'Maandelijks',
            self::QUARTERLY => 'Per kwartaal',
            self::SEMIYEARLY => 'Per halfjaar',
            self::YEARLY => 'Jaarlijks'
        };
    }

    public function toMollie(): string
    {
        return match ($this) {
            self::DAILY => '1 days',
            self::MONTHLY => '1 month',
            self::QUARTERLY => '3 months',
            self::SEMIYEARLY => '6 months',
            self::YEARLY => '12 months'
        };
    }

    public function getLetter(): string
    {
        return match ($this) {
            self::DAILY => 'd',
            self::MONTHLY => 'm',
            self::QUARTERLY => 'k',
            self::SEMIYEARLY => 'h',
            self::YEARLY => 'j'
        };
    }

    public function nextDate($now=null)
    {
        if ($this->getLetter() === 'd'){
            if ($now) return $now->addDay();
            return now()->addDay();
        }
        if ($now) return $now->addMonths($this->value);
        return now()->addMonths($this->value);
    }
}
