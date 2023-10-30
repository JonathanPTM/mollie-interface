<?php

namespace PTM\MollieInterface\Repositories;

use Illuminate\Database\Eloquent\Model;
use Mollie\Api\Types\SequenceType;
use PTM\MollieInterface\models\Payment;
use PTM\MollieInterface\traits\PaymentBuilder;

class SimplePayment
{
    use PaymentBuilder;
    public function __construct(Model $owner, float $total, string $description, array $options = [])
    {
        $this->owner = $owner;
        $this->total = $total;
        $this->options = $options;
        $this->description = $description;
        $this->taxPercentage = config('ptm_subscription.tax', 21);
        $this->sequenceType = SequenceType::SEQUENCETYPE_ONEOFF;
        $this->redirectUrl = url('');
        $urlOptions = $this->options['query'] ?? [];
        $this->webhookUrl = route('ptm_mollie.webhook.payment.standalone', $urlOptions);
    }

    /**
     * @param string $webhookUrl
     */
    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @param String $cardToken
     */
    public function setCardToken(string $cardToken)
    {
        $this->cardToken = $cardToken;
        return $this;
    }

    /**
     * @param string $sequenceType
     */
    public function setSequenceType(string $sequenceType): void
    {
        $this->sequenceType = $sequenceType;
    }

    public function create()
    {
        if (!$this->owner->mollieCustomer || !$this->owner->mollieCustomer->mollie_mandate_id) {
            throw new \Exception("Mollie customer doesn't have mandateID!");
        }
        // Get payment variables
        $payload = $this->getMolliePayload();
        // Create mollie payment
        $this->molliePayment = mollie()->payments()->create($payload);
        // Connect payment to the subscription
        $payment = Payment::create($this->getPaymentPayload());
        // Return payment object
        return ['mollie'=>$this->molliePayment, 'payment'=>$payment];
    }

    public function redirect(): ?string
    {
        if ($this->molliePayment) return $this->molliePayment->redirectUrl;
        return null;
    }

    public function setTax(int $tax)
    {
        $base_price = ($this->total / (100 + $this->taxPercentage)) * 100;
        if ($tax === 0){
            $this->taxPercentage = 0;
            $this->total = $base_price;
            return $this;
        }
        $this->taxPercentage = ($tax > 1 ? $tax : ($tax > 0 ? ($tax * 100) : 0));
        $this->total = $base_price * (1 + ($this->taxPercentage / 100));
        return $this;
    }

    public function setRedirectURL(string $url){
        $this->redirectUrl = $url;
        return $this;
    }

    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
}