<?php
/*
 *  mollie_interface - PaymentMethodString.php Copyright (c) 2023 PTMDevelopment -  Jonathan. All rights reserved.
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

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

trait PaymentMethodString
{
    /**
     * Backwards compatible: split strings into array
     *
     * @param string $method
     *
     * @return string[]
     */
    private function castPaymentMethodString(string $method)
    {
        return collect(explode(',', $method))
            ->map(function ($methodString) {
                return trim($methodString);
            })
            ->filter()
            ->unique()
            ->all();
    }

    /**
     * Create a Mollie Amount array from a Money object.
     *
     * @param float
     * @return array $array
     */
    private function money_to_mollie_array(float $money)
    {
        return [
            'currency' => "EUR",//$money->getCurrency()->getCode(),
            'value' => number_format($money, 2, '.', '')//$moneyFormatter->format($money),
        ];
    }

    /**
     * Create a Money object from a Mollie Amount array.
     *
     * @param int $value
     * @param string $currency
     * @return \Money\Money
     */
    function money(int $value, string $currency)
    {
        return new Money($value, new Currency($currency));
    }

    /**
     * Create a Money object from a decimal string / currency pair.
     *
     * @param string $value
     * @param string $currency
     * @return \Money\Money
     */
    static function decimal_to_money(string $value, string $currency)
    {
        $moneyParser = new DecimalMoneyParser(new ISOCurrencies());

        return $moneyParser->parse($value, new Currency($currency));
    }

    /**
     * Create a Money object from a Mollie Amount array.
     *
     * @param array $array
     * @return \Money\Money
     */
    static function mollie_array_to_money(array $array)
    {
        return self::decimal_to_money($array['value'], $array['currency']);
    }

    /**
     * Create a Money object from a Mollie Amount object.
     *
     * @param object $object
     * @return \Money\Money
     */
    static function mollie_object_to_money(object $object)
    {
        return self::decimal_to_money($object->value, $object->currency);
    }

    /**
     * Format the money as basic decimal
     *
     * @param \Money\Money $money
     * @return string|bool
     */
    function money_to_decimal(Money $money)
    {
        return (new DecimalMoneyFormatter(new ISOCurrencies()))->format($money);
    }
}
